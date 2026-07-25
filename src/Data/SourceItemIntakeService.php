<?php

namespace StudyMentor\ContentEngine\Data;

defined('ABSPATH') || exit;

final class SourceItemIntakeService
{
    private const MAX_RECORDS = 25;
    private const MAX_PAYLOAD_BYTES = 102400;
    private const MAX_TITLE_LENGTH = 500;
    private const MAX_CATEGORY_LENGTH = 150;
    private const MAX_URL_LENGTH = 2048;
    private const ALLOWED_KEYS = array('title', 'url', 'date', 'category');
    private const REQUIRED_KEYS = array('title', 'url', 'date');

    private $sourceRepository;
    private $sourceItemRepository;

    public function __construct(SourceRepository $sourceRepository, SourceItemRepository $sourceItemRepository)
    {
        $this->sourceRepository = $sourceRepository;
        $this->sourceItemRepository = $sourceItemRepository;
    }

    public function preview($sourceId, $rawJson)
    {
        $batch = $this->validateBatch($sourceId, $rawJson);

        return array(
            'source_error' => $batch['source_error'],
            'source' => $batch['source'],
            'payload_error' => $batch['payload_error'],
            'records' => $batch['records'],
            'all_valid' => $batch['all_valid'],
        );
    }

    public function confirm($sourceId, $rawJson)
    {
        $batch = $this->validateBatch($sourceId, $rawJson);

        if ($batch['source_error'] !== '' || $batch['payload_error'] !== '' || !$batch['all_valid']) {
            return array('result' => 'validation_failed', 'inserted' => 0, 'duplicate' => 0);
        }

        $sourceIdInt = (int) $sourceId;
        $inserted = 0;
        $duplicate = 0;

        foreach ($batch['records'] as $record) {
            if ($record['status'] === 'duplicate_existing' || $record['status'] === 'duplicate_batch') {
                $duplicate++;
                continue;
            }

            $insertData = $record['insert_data'];

            if (!is_array($insertData)) {
                return array('result' => 'insert_failed', 'inserted' => $inserted, 'duplicate' => $duplicate);
            }

            $success = $this->sourceItemRepository->insert($insertData);

            if ($success) {
                $inserted++;
                continue;
            }

            $stillMissing = !$this->sourceItemRepository->existsBySourceAndIdentityHash(
                $sourceIdInt,
                (string) $record['identity_hash']
            );

            if (!$stillMissing) {
                $duplicate++;
                continue;
            }

            return array('result' => 'insert_failed', 'inserted' => $inserted, 'duplicate' => $duplicate);
        }

        return array('result' => 'ok', 'inserted' => $inserted, 'duplicate' => $duplicate);
    }

    private function validateBatch($sourceId, $rawJson)
    {
        $result = array(
            'source_error' => '',
            'source' => null,
            'payload_error' => '',
            'records' => array(),
            'all_valid' => false,
        );

        $sourceValidation = $this->validateSource($sourceId);

        if ($sourceValidation['error'] !== '') {
            $result['source_error'] = $sourceValidation['error'];
            return $result;
        }

        $result['source'] = $sourceValidation['source'];

        $payloadValidation = $this->validatePayloadStructure($rawJson);

        if ($payloadValidation['error'] !== '') {
            $result['payload_error'] = $payloadValidation['error'];
            return $result;
        }

        $sourceIdInt = (int) $sourceValidation['source']['id'];
        $allowedDomains = $sourceValidation['source']['allowed_domains'];
        $utcNow = current_time('mysql', true);
        $records = array();
        $seenHashesInBatch = array();

        foreach ($payloadValidation['items'] as $index => $item) {
            $record = $this->buildRecord(
                $index + 1,
                $item,
                $sourceIdInt,
                $allowedDomains,
                $utcNow
            );

            if ($record['status'] !== 'invalid' && $record['identity_hash'] !== '') {
                $hash = $record['identity_hash'];

                if (isset($seenHashesInBatch[$hash])) {
                    $record['status'] = 'duplicate_batch';
                    $record['message'] = 'duplicate_in_batch';
                    $record['insert_data'] = null;
                } else {
                    $seenHashesInBatch[$hash] = true;

                    if ($this->sourceItemRepository->existsBySourceAndIdentityHash($sourceIdInt, $hash)) {
                        $record['status'] = 'duplicate_existing';
                        $record['message'] = 'duplicate_existing';
                        $record['insert_data'] = null;
                    }
                }
            }

            $records[] = $record;
        }

        $result['records'] = $records;

        $allValid = true;

        foreach ($records as $record) {
            if ($record['status'] === 'invalid') {
                $allValid = false;
                break;
            }
        }

        $result['all_valid'] = $allValid;

        return $result;
    }

    private function validateSource($sourceId)
    {
        $id = (int) $sourceId;

        if ($id <= 0) {
            return array('error' => 'invalid_source_id', 'source' => null);
        }

        $source = $this->sourceRepository->findById($id);

        if ($source === null) {
            return array('error' => 'source_not_found', 'source' => null);
        }

        if (!isset($source['manual_only']) || (int) $source['manual_only'] !== 1) {
            return array('error' => 'source_not_manual_only', 'source' => null);
        }

        $allowedDomainsRaw = isset($source['allowed_domains']) ? (string) $source['allowed_domains'] : '';
        $decodedDomains = json_decode($allowedDomainsRaw, true);

        if (!is_array($decodedDomains) || $decodedDomains === array()) {
            return array('error' => 'source_invalid_allowed_domains', 'source' => null);
        }

        $normalizedDomains = array();

        foreach ($decodedDomains as $domain) {
            if (!is_string($domain) || $domain === '') {
                return array('error' => 'source_invalid_allowed_domains', 'source' => null);
            }

            $normalizedDomain = strtolower(rtrim(trim($domain), '.'));

            if ($normalizedDomain === '') {
                return array('error' => 'source_invalid_allowed_domains', 'source' => null);
            }

            $normalizedDomains[] = $normalizedDomain;
        }

        return array(
            'error' => '',
            'source' => array(
                'id' => $id,
                'name' => isset($source['name']) ? (string) $source['name'] : '',
                'allowed_domains' => $normalizedDomains,
            ),
        );
    }

    private function validatePayloadStructure($rawJson)
    {
        if (!is_string($rawJson)) {
            return array('error' => 'invalid_payload', 'items' => array());
        }

        if (strlen($rawJson) === 0) {
            return array('error' => 'empty_payload', 'items' => array());
        }

        if (strlen($rawJson) > self::MAX_PAYLOAD_BYTES) {
            return array('error' => 'payload_too_large', 'items' => array());
        }

        if (!$this->isValidUtf8($rawJson)) {
            return array('error' => 'invalid_utf8', 'items' => array());
        }

        $decoded = json_decode($rawJson, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return array('error' => 'invalid_json', 'items' => array());
        }

        if (!is_array($decoded)) {
            return array('error' => 'not_array', 'items' => array());
        }

        if (!$this->isNumericList($decoded)) {
            return array('error' => 'not_list', 'items' => array());
        }

        $count = count($decoded);

        if ($count === 0) {
            return array('error' => 'empty_batch', 'items' => array());
        }

        if ($count > self::MAX_RECORDS) {
            return array('error' => 'too_many_records', 'items' => array());
        }

        return array('error' => '', 'items' => $decoded);
    }

    private function buildRecord($rowNumber, $item, $sourceId, array $allowedDomains, $utcNow)
    {
        $record = array(
            'index' => $rowNumber,
            'status' => 'invalid',
            'message' => '',
            'title' => '',
            'category' => '',
            'date' => '',
            'canonical_url' => '',
            'identity_hash' => '',
            'insert_data' => null,
        );

        if (!is_array($item)) {
            $record['message'] = 'not_object';
            return $record;
        }

        foreach (array_keys($item) as $key) {
            if (!in_array($key, self::ALLOWED_KEYS, true)) {
                $record['message'] = 'unexpected_key';
                return $record;
            }
        }

        foreach (self::REQUIRED_KEYS as $key) {
            if (!array_key_exists($key, $item)) {
                $record['message'] = 'missing_required_key';
                return $record;
            }
        }

        $record['title'] = is_string($item['title']) ? $item['title'] : '';
        $record['date'] = is_string($item['date']) ? $item['date'] : '';
        $record['category'] = (isset($item['category']) && is_string($item['category']))
            ? $item['category']
            : '';

        $titleResult = $this->validateTitle($item['title']);

        if ($titleResult['error'] !== '') {
            $record['message'] = $titleResult['error'];
            return $record;
        }

        $record['title'] = $titleResult['value'];

        $hasCategory = array_key_exists('category', $item);
        $categoryResult = $this->validateCategory($hasCategory ? $item['category'] : '', $hasCategory);

        if ($categoryResult['error'] !== '') {
            $record['message'] = $categoryResult['error'];
            return $record;
        }

        $record['category'] = $categoryResult['value'];

        $dateResult = $this->validateDate($item['date']);

        if ($dateResult['error'] !== '') {
            $record['message'] = $dateResult['error'];
            return $record;
        }

        $record['date'] = $dateResult['value'];

        $urlResult = $this->validateUrl($item['url'], $allowedDomains);

        if ($urlResult['error'] !== '') {
            $record['message'] = $urlResult['error'];
            return $record;
        }

        $record['canonical_url'] = $urlResult['value'];

        $identityHash = hash('sha256', $urlResult['value']);

        $rawPayloadArray = array(
            'schema_version' => 1,
            'intake_method' => 'manual_json',
            'original_title' => $titleResult['value'],
            'original_url' => (string) $item['url'],
            'publication_date' => $dateResult['value'],
            'date_precision' => 'day',
            'category' => $categoryResult['value'],
        );

        $encodedPayload = wp_json_encode($rawPayloadArray);

        if (!is_string($encodedPayload) || $encodedPayload === '') {
            $record['message'] = 'payload_encoding_failed';
            return $record;
        }

        $record['status'] = 'new';
        $record['message'] = 'valid';
        $record['identity_hash'] = $identityHash;
        $record['insert_data'] = array(
            'source_id' => $sourceId,
            'identity_hash' => $identityHash,
            'identity_basis' => 'manual_url',
            'source_guid' => null,
            'canonical_url' => $urlResult['value'],
            'source_published_at_utc' => $dateResult['value'] . ' 00:00:00',
            'raw_title' => $titleResult['value'],
            'content_hash' => null,
            'raw_payload' => $encodedPayload,
            'revision_no' => 1,
            'first_seen_at_utc' => $utcNow,
            'last_seen_at_utc' => $utcNow,
            'created_at_utc' => $utcNow,
            'updated_at_utc' => $utcNow,
        );

        return $record;
    }

    private function validateTitle($rawTitle)
    {
        if (!is_string($rawTitle)) {
            return array('value' => '', 'error' => 'invalid_title');
        }

        $collapsed = $this->collapseWhitespace($rawTitle);

        if ($collapsed === '') {
            return array('value' => '', 'error' => 'invalid_title');
        }

        if ($this->safeStrLen($collapsed) > self::MAX_TITLE_LENGTH) {
            return array('value' => '', 'error' => 'invalid_title');
        }

        if (!$this->isPlainText($collapsed)) {
            return array('value' => '', 'error' => 'invalid_title');
        }

        return array('value' => $collapsed, 'error' => '');
    }

    private function validateCategory($rawCategory, $present)
    {
        if (!$present) {
            return array('value' => '', 'error' => '');
        }

        if (!is_string($rawCategory)) {
            return array('value' => '', 'error' => 'invalid_category');
        }

        $collapsed = $this->collapseWhitespace($rawCategory);

        if ($collapsed === '') {
            return array('value' => '', 'error' => '');
        }

        if ($this->safeStrLen($collapsed) > self::MAX_CATEGORY_LENGTH) {
            return array('value' => '', 'error' => 'invalid_category');
        }

        if (!$this->isPlainText($collapsed)) {
            return array('value' => '', 'error' => 'invalid_category');
        }

        return array('value' => $collapsed, 'error' => '');
    }

    private function validateDate($rawDate)
    {
        if (!is_string($rawDate)) {
            return array('value' => '', 'error' => 'invalid_date');
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawDate) !== 1) {
            return array('value' => '', 'error' => 'invalid_date');
        }

        $year = (int) substr($rawDate, 0, 4);
        $month = (int) substr($rawDate, 5, 2);
        $day = (int) substr($rawDate, 8, 2);

        if (!checkdate($month, $day, $year)) {
            return array('value' => '', 'error' => 'invalid_date');
        }

        return array('value' => $rawDate, 'error' => '');
    }

    private function validateUrl($rawUrl, array $allowedDomains)
    {
        if (!is_string($rawUrl)) {
            return array('value' => '', 'error' => 'invalid_url');
        }

        $trimmed = trim($rawUrl);

        if ($trimmed === '') {
            return array('value' => '', 'error' => 'invalid_url');
        }

        if (strlen($trimmed) > self::MAX_URL_LENGTH) {
            return array('value' => '', 'error' => 'url_too_long');
        }

        $parts = wp_parse_url($trimmed);

        if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
            return array('value' => '', 'error' => 'invalid_url');
        }

        $scheme = strtolower((string) $parts['scheme']);

        if ($scheme !== 'http' && $scheme !== 'https') {
            return array('value' => '', 'error' => 'invalid_url');
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            return array('value' => '', 'error' => 'url_credentials');
        }

        $host = strtolower(trim((string) $parts['host']));
        $host = rtrim($host, '.');

        if ($host === '') {
            return array('value' => '', 'error' => 'invalid_url');
        }

        if (preg_match('/[^\x00-\x7F]/', $host) === 1) {
            if (!function_exists('idn_to_ascii')) {
                return array('value' => '', 'error' => 'invalid_host');
            }

            if (defined('INTL_IDNA_VARIANT_UTS46') && defined('IDNA_DEFAULT')) {
                $converted = idn_to_ascii($host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
            } else {
                $converted = idn_to_ascii($host);
            }

            if (!is_string($converted) || $converted === '') {
                return array('value' => '', 'error' => 'invalid_host');
            }

            $host = strtolower($converted);
        }

        $port = isset($parts['port']) ? (int) $parts['port'] : null;

        if ($port === 80 && $scheme === 'http') {
            $port = null;
        }

        if ($port === 443 && $scheme === 'https') {
            $port = null;
        }

        $path = isset($parts['path']) ? (string) $parts['path'] : '';
        $path = $this->removeDotSegments($path);

        $query = isset($parts['query']) ? (string) $parts['query'] : '';

        $canonical = $scheme . '://' . $host;

        if ($port !== null) {
            $canonical .= ':' . $port;
        }

        $canonical .= $path;

        if ($query !== '') {
            $canonical .= '?' . $query;
        }

        if (!in_array($host, $allowedDomains, true)) {
            return array('value' => '', 'error' => 'domain_not_allowed');
        }

        return array('value' => $canonical, 'host' => $host, 'error' => '');
    }

    private function removeDotSegments($path)
    {
        if ($path === '') {
            return '';
        }

        $input = $path;
        $output = '';

        while ($input !== '') {
            if (strpos($input, '../') === 0) {
                $input = substr($input, 3);
            } elseif (strpos($input, './') === 0) {
                $input = substr($input, 2);
            } elseif (strpos($input, '/./') === 0) {
                $input = '/' . substr($input, 3);
            } elseif ($input === '/.') {
                $input = '/';
            } elseif (strpos($input, '/../') === 0) {
                $input = '/' . substr($input, 4);
                $lastSlash = strrpos($output, '/');
                $output = $lastSlash === false ? '' : substr($output, 0, $lastSlash);
            } elseif ($input === '/..') {
                $input = '/';
                $lastSlash = strrpos($output, '/');
                $output = $lastSlash === false ? '' : substr($output, 0, $lastSlash);
            } elseif ($input === '.' || $input === '..') {
                $input = '';
            } else {
                if (substr($input, 0, 1) === '/') {
                    $nextSlash = strpos($input, '/', 1);
                } else {
                    $nextSlash = strpos($input, '/');
                }

                if ($nextSlash === false) {
                    $segment = $input;
                    $input = '';
                } else {
                    $segment = substr($input, 0, $nextSlash);
                    $input = substr($input, $nextSlash);
                }

                $output .= $segment;
            }
        }

        return $output;
    }

    private function collapseWhitespace($value)
    {
        $trimmed = trim((string) $value);

        if ($trimmed === '') {
            return '';
        }

        $collapsed = preg_replace('/[\s\x{00A0}]+/u', ' ', $trimmed);

        if (!is_string($collapsed)) {
            return '';
        }

        return trim($collapsed);
    }

    private function isPlainText($value)
    {
        if (preg_match('/[<>]/', $value) === 1) {
            return false;
        }

        if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value) === 1) {
            return false;
        }

        return true;
    }

    private function safeStrLen($value)
    {
        if (function_exists('mb_strlen')) {
            return mb_strlen($value, 'UTF-8');
        }

        return strlen($value);
    }

    private function isValidUtf8($value)
    {
        if (function_exists('mb_check_encoding')) {
            return mb_check_encoding($value, 'UTF-8');
        }

        return preg_match('//u', $value) === 1;
    }

    private function isNumericList(array $arr)
    {
        $expectedIndex = 0;

        foreach (array_keys($arr) as $key) {
            if ($key !== $expectedIndex) {
                return false;
            }

            $expectedIndex++;
        }

        return true;
    }
}
