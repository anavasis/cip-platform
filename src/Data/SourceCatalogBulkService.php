<?php

namespace StudyMentor\ContentEngine\Data;

defined('ABSPATH') || exit;

final class SourceCatalogBulkService
{
    private const MAX_RECORDS = 80;
    private const MAX_PAYLOAD_BYTES = 102400;
    private const MAX_URL_LENGTH = 2048;
    private const SOURCE_TYPES = array('rss', 'atom', 'html', 'manual');
    private const SLUG_PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';
    private const ALLOWED_KEYS = array(
        'slug',
        'name',
        'source_type',
        'feed_url',
        'base_url',
        'allowed_domains',
        'parser_profile',
    );
    private const REQUIRED_KEYS = array(
        'slug',
        'name',
        'source_type',
        'feed_url',
        'allowed_domains',
    );

    private $repository;

    public function __construct(SourceRepository $repository)
    {
        $this->repository = $repository;
    }

    public function preview($rawJson)
    {
        $batch = $this->validateBatch($rawJson);
        $rows = array();

        foreach ($batch['rows'] as $row) {
            $rows[] = array(
                'index' => (int) $row['index'],
                'status' => (string) $row['status'],
                'message' => (string) $row['message'],
                'slug' => (string) $row['slug'],
                'name' => (string) $row['name'],
                'source_type' => (string) $row['source_type'],
                'feed_url' => (string) $row['feed_url'],
                'allowed_domains' => (string) $row['allowed_domains_display'],
                'parser_profile' => $row['parser_profile'] === null
                    ? ''
                    : (string) $row['parser_profile'],
            );
        }

        return array(
            'ok' => $batch['structurally_valid'],
            'structurally_valid' => $batch['structurally_valid'],
            'all_valid' => $batch['all_valid'],
            'rows' => $rows,
            'total' => $batch['total'],
            'ready' => $batch['ready'],
            'duplicate' => $batch['duplicate'],
            'invalid' => $batch['invalid'],
            'code' => $batch['code'],
        );
    }

    public function confirm($rawJson)
    {
        $batch = $this->validateBatch($rawJson);

        if (!$batch['structurally_valid'] || !$batch['all_valid']) {
            return array(
                'result' => 'validation_failed',
                'inserted' => 0,
                'duplicate' => (int) $batch['duplicate'],
                'invalid' => (int) $batch['invalid'],
            );
        }

        $inserted = 0;
        $duplicate = 0;

        foreach ($batch['rows'] as $row) {
            if ($row['status'] === 'duplicate_existing' || $row['status'] === 'duplicate_batch') {
                $duplicate++;
                continue;
            }

            if ($row['status'] !== 'ready' || !is_array($row['insert_data'])) {
                return array(
                    'result' => 'insert_failed',
                    'inserted' => $inserted,
                    'duplicate' => $duplicate,
                    'invalid' => 0,
                );
            }

            $insertId = $this->repository->insert($row['insert_data']);

            if ($insertId !== false) {
                $inserted++;
                continue;
            }

            $slug = isset($row['insert_data']['slug']) ? (string) $row['insert_data']['slug'] : '';
            $feedHash = isset($row['insert_data']['feed_url_hash'])
                ? (string) $row['insert_data']['feed_url_hash']
                : '';

            if (
                ($slug !== '' && $this->repository->slugExists($slug))
                || ($feedHash !== '' && $this->repository->feedHashExists($feedHash))
            ) {
                $duplicate++;
                continue;
            }

            return array(
                'result' => 'insert_failed',
                'inserted' => $inserted,
                'duplicate' => $duplicate,
                'invalid' => 0,
            );
        }

        return array(
            'result' => 'confirmed',
            'inserted' => $inserted,
            'duplicate' => $duplicate,
            'invalid' => 0,
        );
    }

    private function validateBatch($rawJson)
    {
        $result = array(
            'structurally_valid' => false,
            'all_valid' => false,
            'rows' => array(),
            'total' => 0,
            'ready' => 0,
            'duplicate' => 0,
            'invalid' => 0,
            'code' => 'invalid_payload',
        );

        $payloadValidation = $this->validatePayloadStructure($rawJson);

        if ($payloadValidation['error'] !== '') {
            $result['code'] = $payloadValidation['error'];
            return $result;
        }

        $result['structurally_valid'] = true;
        $result['code'] = 'ok';

        $utcNow = current_time('mysql', true);
        $rows = array();
        $seenSlugs = array();
        $seenHashes = array();
        $ready = 0;
        $duplicate = 0;
        $invalid = 0;

        foreach ($payloadValidation['items'] as $index => $item) {
            $row = $this->buildRow($index + 1, $item, $utcNow);

            if ($row['status'] !== 'invalid') {
                $slug = (string) $row['slug'];
                $feedHash = (string) $row['feed_url_hash'];

                if (isset($seenSlugs[$slug]) || isset($seenHashes[$feedHash])) {
                    $row['status'] = 'duplicate_batch';
                    $row['message'] = 'duplicate_batch';
                    $row['insert_data'] = null;
                } else {
                    $seenSlugs[$slug] = true;
                    $seenHashes[$feedHash] = true;

                    if (
                        $this->repository->slugExists($slug)
                        || $this->repository->feedHashExists($feedHash)
                    ) {
                        $row['status'] = 'duplicate_existing';
                        $row['message'] = 'duplicate_existing';
                        $row['insert_data'] = null;
                    }
                }
            }

            if ($row['status'] === 'ready') {
                $ready++;
            } elseif (
                $row['status'] === 'duplicate_existing'
                || $row['status'] === 'duplicate_batch'
            ) {
                $duplicate++;
            } else {
                $invalid++;
            }

            $rows[] = $row;
        }

        $result['rows'] = $rows;
        $result['total'] = count($rows);
        $result['ready'] = $ready;
        $result['duplicate'] = $duplicate;
        $result['invalid'] = $invalid;
        $result['all_valid'] = ($invalid === 0);

        if ($invalid > 0) {
            $result['code'] = 'has_invalid_rows';
        }

        return $result;
    }

    private function buildRow($rowNumber, $item, $utcNow)
    {
        $row = array(
            'index' => $rowNumber,
            'status' => 'invalid',
            'message' => '',
            'slug' => '',
            'name' => '',
            'source_type' => '',
            'feed_url' => '',
            'feed_url_hash' => '',
            'allowed_domains_display' => '',
            'parser_profile' => null,
            'insert_data' => null,
        );

        if (!is_array($item)) {
            $row['message'] = 'not_object';
            return $row;
        }

        foreach (array_keys($item) as $key) {
            if (!is_string($key) || !in_array($key, self::ALLOWED_KEYS, true)) {
                $row['message'] = 'unexpected_key';
                return $row;
            }
        }

        foreach (self::REQUIRED_KEYS as $key) {
            if (!array_key_exists($key, $item)) {
                $row['message'] = 'missing_required_key';
                return $row;
            }
        }

        $slugResult = $this->validateSlug(isset($item['slug']) ? $item['slug'] : null);

        if ($slugResult['error'] !== '') {
            $row['message'] = $slugResult['error'];
            return $row;
        }

        $row['slug'] = $slugResult['value'];

        $nameResult = $this->validateName(isset($item['name']) ? $item['name'] : null);

        if ($nameResult['error'] !== '') {
            $row['message'] = $nameResult['error'];
            return $row;
        }

        $row['name'] = $nameResult['value'];

        $typeResult = $this->validateSourceType(
            isset($item['source_type']) ? $item['source_type'] : null
        );

        if ($typeResult['error'] !== '') {
            $row['message'] = $typeResult['error'];
            return $row;
        }

        $row['source_type'] = $typeResult['value'];

        $feedUrlResult = $this->validateRequiredUrl(
            isset($item['feed_url']) ? $item['feed_url'] : null,
            'invalid_feed_url'
        );

        if ($feedUrlResult['error'] !== '') {
            $row['message'] = $feedUrlResult['error'];
            return $row;
        }

        $row['feed_url'] = $feedUrlResult['value'];
        $feedHash = hash('sha256', $feedUrlResult['value']);
        $row['feed_url_hash'] = $feedHash;

        $hasBaseUrl = array_key_exists('base_url', $item);
        $baseUrlResult = $this->validateOptionalUrl(
            $hasBaseUrl ? $item['base_url'] : null,
            $hasBaseUrl
        );

        if ($baseUrlResult['error'] !== '') {
            $row['message'] = $baseUrlResult['error'];
            return $row;
        }

        $domainsResult = $this->validateAllowedDomains(
            isset($item['allowed_domains']) ? $item['allowed_domains'] : null
        );

        if ($domainsResult['error'] !== '') {
            $row['message'] = $domainsResult['error'];
            return $row;
        }

        $row['allowed_domains_display'] = implode(', ', $domainsResult['domains']);

        $feedHost = $this->hostFromNormalizedUrl($feedUrlResult['value']);

        if ($feedHost === '' || !in_array($feedHost, $domainsResult['domains'], true)) {
            $row['message'] = 'host_not_allowed';
            return $row;
        }

        if ($baseUrlResult['value'] !== null) {
            $baseHost = $this->hostFromNormalizedUrl($baseUrlResult['value']);

            if ($baseHost === '' || !in_array($baseHost, $domainsResult['domains'], true)) {
                $row['message'] = 'host_not_allowed';
                return $row;
            }
        }

        $hasParserProfile = array_key_exists('parser_profile', $item);
        $parserResult = $this->validateParserProfile(
            $hasParserProfile ? $item['parser_profile'] : null,
            $hasParserProfile
        );

        if ($parserResult['error'] !== '') {
            $row['message'] = $parserResult['error'];
            return $row;
        }

        $row['parser_profile'] = $parserResult['value'];

        $row['status'] = 'ready';
        $row['message'] = 'ready';
        $row['insert_data'] = array(
            'slug' => $slugResult['value'],
            'name' => $nameResult['value'],
            'source_type' => $typeResult['value'],
            'base_url' => $baseUrlResult['value'],
            'feed_url' => $feedUrlResult['value'],
            'feed_url_hash' => $feedHash,
            'allowed_domains' => $domainsResult['value'],
            'parser_profile' => $parserResult['value'],
            'enabled' => 0,
            'manual_only' => 1,
            'created_at_utc' => $utcNow,
            'updated_at_utc' => $utcNow,
        );

        return $row;
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

        $decoded = json_decode($rawJson, false);

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

        $items = array();

        foreach ($decoded as $item) {
            if ($item instanceof \stdClass) {
                $items[] = get_object_vars($item);
                continue;
            }

            $items[] = $item;
        }

        return array('error' => '', 'items' => $items);
    }

    private function validateSlug($rawSlug)
    {
        if (!is_string($rawSlug)) {
            return array('value' => '', 'error' => 'invalid_slug');
        }

        $slug = sanitize_title(wp_unslash($rawSlug));

        if ($slug === '') {
            return array('value' => '', 'error' => 'invalid_slug');
        }

        if (strlen($slug) > 128) {
            return array('value' => '', 'error' => 'invalid_slug');
        }

        if (preg_match(self::SLUG_PATTERN, $slug) !== 1) {
            return array('value' => '', 'error' => 'invalid_slug');
        }

        return array('value' => $slug, 'error' => '');
    }

    private function validateName($rawName)
    {
        if (!is_string($rawName)) {
            return array('value' => '', 'error' => 'invalid_name');
        }

        if (preg_match('/[<>]/', $rawName) === 1) {
            return array('value' => '', 'error' => 'invalid_name');
        }

        if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $rawName) === 1) {
            return array('value' => '', 'error' => 'invalid_name');
        }

        $name = sanitize_text_field(wp_unslash($rawName));
        $name = trim($name);

        if ($name === '') {
            return array('value' => '', 'error' => 'invalid_name');
        }

        if (strlen($name) > 191) {
            return array('value' => '', 'error' => 'invalid_name');
        }

        return array('value' => $name, 'error' => '');
    }

    private function validateSourceType($rawType)
    {
        if (!is_string($rawType)) {
            return array('value' => '', 'error' => 'invalid_source_type');
        }

        $type = sanitize_key(wp_unslash($rawType));

        if ($type === '' || !in_array($type, self::SOURCE_TYPES, true)) {
            return array('value' => '', 'error' => 'invalid_source_type');
        }

        return array('value' => $type, 'error' => '');
    }

    private function validateOptionalUrl($rawUrl, $present)
    {
        if (!$present || $rawUrl === null) {
            return array('value' => null, 'error' => '');
        }

        if (!is_string($rawUrl)) {
            return array('value' => '', 'error' => 'invalid_base_url');
        }

        $trimmed = trim(wp_unslash($rawUrl));

        if ($trimmed === '') {
            return array('value' => null, 'error' => '');
        }

        if (strlen($trimmed) > self::MAX_URL_LENGTH) {
            return array('value' => '', 'error' => 'invalid_base_url');
        }

        $normalized = $this->normalizeUrl($trimmed);

        if ($normalized === '' || strlen($normalized) > self::MAX_URL_LENGTH) {
            return array('value' => '', 'error' => 'invalid_base_url');
        }

        return array('value' => $normalized, 'error' => '');
    }

    private function validateRequiredUrl($rawUrl, $errorCode)
    {
        if (!is_string($rawUrl)) {
            return array('value' => '', 'error' => $errorCode);
        }

        $trimmed = trim(wp_unslash($rawUrl));

        if ($trimmed === '') {
            return array('value' => '', 'error' => $errorCode);
        }

        if (strlen($trimmed) > self::MAX_URL_LENGTH) {
            return array('value' => '', 'error' => $errorCode);
        }

        $normalized = $this->normalizeUrl($trimmed);

        if ($normalized === '' || strlen($normalized) > self::MAX_URL_LENGTH) {
            return array('value' => '', 'error' => $errorCode);
        }

        return array('value' => $normalized, 'error' => '');
    }

    private function validateParserProfile($rawProfile, $present)
    {
        if (!$present || $rawProfile === null) {
            return array('value' => null, 'error' => '');
        }

        if (!is_string($rawProfile)) {
            return array('value' => '', 'error' => 'invalid_parser_profile');
        }

        $profile = sanitize_key(wp_unslash($rawProfile));

        if ($profile === '') {
            return array('value' => null, 'error' => '');
        }

        if (strlen($profile) > 64) {
            return array('value' => '', 'error' => 'invalid_parser_profile');
        }

        return array('value' => $profile, 'error' => '');
    }

    private function validateAllowedDomains($rawDomains)
    {
        if (!is_array($rawDomains) || !$this->isNumericList($rawDomains) || $rawDomains === array()) {
            return array('value' => '', 'domains' => array(), 'error' => 'invalid_allowed_domains');
        }

        $normalized = array();
        $seen = array();

        foreach ($rawDomains as $line) {
            if (!is_string($line)) {
                return array('value' => '', 'domains' => array(), 'error' => 'invalid_allowed_domains');
            }

            $domain = $this->normalizeDomainLine($line);

            if ($domain === '') {
                return array('value' => '', 'domains' => array(), 'error' => 'invalid_allowed_domains');
            }

            if (strpos($domain, '*') !== false) {
                return array('value' => '', 'domains' => array(), 'error' => 'invalid_allowed_domains');
            }

            if (strpos($domain, ':') !== false) {
                return array('value' => '', 'domains' => array(), 'error' => 'invalid_allowed_domains');
            }

            if (!$this->isValidHostname($domain)) {
                return array('value' => '', 'domains' => array(), 'error' => 'invalid_allowed_domains');
            }

            if (isset($seen[$domain])) {
                continue;
            }

            $seen[$domain] = true;
            $normalized[] = $domain;
        }

        if ($normalized === array()) {
            return array('value' => '', 'domains' => array(), 'error' => 'invalid_allowed_domains');
        }

        $json = wp_json_encode(array_values($normalized));

        if (!is_string($json) || $json === '') {
            return array('value' => '', 'domains' => array(), 'error' => 'invalid_allowed_domains');
        }

        return array('value' => $json, 'domains' => $normalized, 'error' => '');
    }

    private function normalizeDomainLine($line)
    {
        $domain = strtolower(trim((string) $line));

        if ($domain === '') {
            return '';
        }

        if (strpos($domain, '://') !== false) {
            $parsed = wp_parse_url($domain);

            if (is_array($parsed) && isset($parsed['host'])) {
                $domain = strtolower($parsed['host']);
            }
        }

        $domain = preg_replace('#/.*$#', '', $domain);
        $domain = preg_replace('#\?.*$#', '', $domain);
        $domain = preg_replace('#\#.*$#', '', $domain);
        $domain = rtrim($domain, '.');

        return trim($domain);
    }

    private function isValidHostname($hostname)
    {
        if ($hostname === '' || strlen($hostname) > 253) {
            return false;
        }

        if (function_exists('filter_var')) {
            return filter_var($hostname, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false;
        }

        return preg_match('/^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?(\.[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?)+$/', $hostname) === 1
            || preg_match('/^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?$/', $hostname) === 1;
    }

    private function normalizeUrl($url)
    {
        $trimmed = trim((string) $url);

        if ($trimmed === '') {
            return '';
        }

        $sanitized = esc_url_raw($trimmed);

        if ($sanitized === '') {
            return '';
        }

        $parsed = wp_parse_url($sanitized);

        if (!is_array($parsed) || !isset($parsed['scheme'], $parsed['host'])) {
            return '';
        }

        if (isset($parsed['user']) || isset($parsed['pass'])) {
            return '';
        }

        $scheme = strtolower($parsed['scheme']);

        if ($scheme !== 'http' && $scheme !== 'https') {
            return '';
        }

        $host = strtolower($parsed['host']);
        $port = isset($parsed['port']) ? (int) $parsed['port'] : null;

        if ($port === 80 && $scheme === 'http') {
            $port = null;
        }

        if ($port === 443 && $scheme === 'https') {
            $port = null;
        }

        $path = isset($parsed['path']) ? $parsed['path'] : '';

        if ($path !== '') {
            $path = preg_replace('#/+#', '/', $path);

            if ($path !== '/' && substr($path, -1) === '/') {
                $path = rtrim($path, '/');
            }
        }

        $query = isset($parsed['query']) ? '?' . $parsed['query'] : '';

        $normalized = $scheme . '://' . $host;

        if ($port !== null) {
            $normalized .= ':' . $port;
        }

        $normalized .= $path . $query;

        return $normalized;
    }

    private function hostFromNormalizedUrl($url)
    {
        $parsed = wp_parse_url((string) $url);

        if (!is_array($parsed) || !isset($parsed['host']) || !is_string($parsed['host'])) {
            return '';
        }

        return strtolower($parsed['host']);
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
