<?php

namespace StudyMentor\ContentEngine\Data;

defined('ABSPATH') || exit;

final class SourceRegistryService
{
    private const SOURCE_TYPES = array('rss', 'atom', 'html', 'manual');
    private const SLUG_PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    private $repository;

    public function __construct(SourceRepository $repository)
    {
        $this->repository = $repository;
    }

    public function create(array $input)
    {
        $slugResult = $this->validateSlug(isset($input['slug']) ? $input['slug'] : '', 0, true);

        if ($slugResult['error'] !== '') {
            return array('success' => false, 'error' => $slugResult['error']);
        }

        $nameResult = $this->validateName(isset($input['name']) ? $input['name'] : '');

        if ($nameResult['error'] !== '') {
            return array('success' => false, 'error' => $nameResult['error']);
        }

        $typeResult = $this->validateSourceType(isset($input['source_type']) ? $input['source_type'] : '');

        if ($typeResult['error'] !== '') {
            return array('success' => false, 'error' => $typeResult['error']);
        }

        $baseUrlResult = $this->validateOptionalUrl(isset($input['base_url']) ? $input['base_url'] : '');

        if ($baseUrlResult['error'] !== '') {
            return array('success' => false, 'error' => $baseUrlResult['error']);
        }

        $feedUrlResult = $this->validateRequiredUrl(isset($input['feed_url']) ? $input['feed_url'] : '');

        if ($feedUrlResult['error'] !== '') {
            return array('success' => false, 'error' => $feedUrlResult['error']);
        }

        if ($this->repository->slugExists($slugResult['value'])) {
            return array('success' => false, 'error' => 'duplicate_slug');
        }

        $feedHash = hash('sha256', $feedUrlResult['value']);

        if ($this->repository->feedHashExists($feedHash)) {
            return array('success' => false, 'error' => 'duplicate_feed_url');
        }

        $domainsResult = $this->validateAllowedDomains(isset($input['allowed_domains']) ? $input['allowed_domains'] : '');

        if ($domainsResult['error'] !== '') {
            return array('success' => false, 'error' => $domainsResult['error']);
        }

        $parserResult = $this->validateParserProfile(isset($input['parser_profile']) ? $input['parser_profile'] : '');

        if ($parserResult['error'] !== '') {
            return array('success' => false, 'error' => 'validation');
        }

        $utcNow = current_time('mysql', true);

        $insertId = $this->repository->insert(array(
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
        ));

        if ($insertId === false) {
            return array('success' => false, 'error' => 'database');
        }

        return array('success' => true, 'id' => $insertId);
    }

    public function update($id, array $input)
    {
        $sourceId = (int) $id;

        if ($sourceId <= 0) {
            return array('success' => false, 'error' => 'invalid_id');
        }

        $existing = $this->repository->findById($sourceId);

        if ($existing === null) {
            return array('success' => false, 'error' => 'not_found');
        }

        $nameResult = $this->validateName(isset($input['name']) ? $input['name'] : '');

        if ($nameResult['error'] !== '') {
            return array('success' => false, 'error' => $nameResult['error']);
        }

        $typeResult = $this->validateSourceType(isset($input['source_type']) ? $input['source_type'] : '');

        if ($typeResult['error'] !== '') {
            return array('success' => false, 'error' => $typeResult['error']);
        }

        $baseUrlResult = $this->validateOptionalUrl(isset($input['base_url']) ? $input['base_url'] : '');

        if ($baseUrlResult['error'] !== '') {
            return array('success' => false, 'error' => $baseUrlResult['error']);
        }

        $feedUrlResult = $this->validateRequiredUrl(isset($input['feed_url']) ? $input['feed_url'] : '');

        if ($feedUrlResult['error'] !== '') {
            return array('success' => false, 'error' => $feedUrlResult['error']);
        }

        $feedHash = hash('sha256', $feedUrlResult['value']);

        if ($this->repository->feedHashExists($feedHash, $sourceId)) {
            return array('success' => false, 'error' => 'duplicate_feed_url');
        }

        $domainsResult = $this->validateAllowedDomains(isset($input['allowed_domains']) ? $input['allowed_domains'] : '');

        if ($domainsResult['error'] !== '') {
            return array('success' => false, 'error' => $domainsResult['error']);
        }

        $parserResult = $this->validateParserProfile(isset($input['parser_profile']) ? $input['parser_profile'] : '');

        if ($parserResult['error'] !== '') {
            return array('success' => false, 'error' => 'validation');
        }

        $updated = $this->repository->update($sourceId, array(
            'name' => $nameResult['value'],
            'source_type' => $typeResult['value'],
            'base_url' => $baseUrlResult['value'],
            'feed_url' => $feedUrlResult['value'],
            'feed_url_hash' => $feedHash,
            'allowed_domains' => $domainsResult['value'],
            'parser_profile' => $parserResult['value'],
            'manual_only' => 1,
            'updated_at_utc' => current_time('mysql', true),
        ));

        if (!$updated) {
            return array('success' => false, 'error' => 'database');
        }

        return array('success' => true, 'id' => $sourceId);
    }

    public function toggle($id, $enabled)
    {
        $sourceId = (int) $id;
        $enabledValue = (int) $enabled;

        if ($sourceId <= 0) {
            return array('success' => false, 'error' => 'invalid_id');
        }

        if ($enabledValue !== 0 && $enabledValue !== 1) {
            return array('success' => false, 'error' => 'validation');
        }

        $existing = $this->repository->findById($sourceId);

        if ($existing === null) {
            return array('success' => false, 'error' => 'not_found');
        }

        $updated = $this->repository->setEnabled($sourceId, $enabledValue);

        if (!$updated) {
            return array('success' => false, 'error' => 'database');
        }

        return array(
            'success' => true,
            'id' => $sourceId,
            'enabled' => $enabledValue,
        );
    }

    public function decodeAllowedDomainsForDisplay($jsonValue)
    {
        if (!is_string($jsonValue) || $jsonValue === '') {
            return '';
        }

        $decoded = json_decode($jsonValue, true);

        if (!is_array($decoded)) {
            return '';
        }

        $lines = array();

        foreach ($decoded as $domain) {
            if (is_string($domain) && $domain !== '') {
                $lines[] = $domain;
            }
        }

        return implode("\n", $lines);
    }

    private function validateSlug($rawSlug, $excludeId, $required)
    {
        $slug = sanitize_title(wp_unslash((string) $rawSlug));

        if ($slug === '') {
            return array(
                'value' => '',
                'error' => $required ? 'invalid_slug' : '',
            );
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
        $name = sanitize_text_field(wp_unslash((string) $rawName));

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
        $type = sanitize_key(wp_unslash((string) $rawType));

        if ($type === '' || !in_array($type, self::SOURCE_TYPES, true)) {
            return array('value' => '', 'error' => 'invalid_source_type');
        }

        return array('value' => $type, 'error' => '');
    }

    private function validateOptionalUrl($rawUrl)
    {
        $trimmed = trim(wp_unslash((string) $rawUrl));

        if ($trimmed === '') {
            return array('value' => null, 'error' => '');
        }

        $normalized = $this->normalizeUrl($trimmed);

        if ($normalized === '') {
            return array('value' => '', 'error' => 'invalid_base_url');
        }

        return array('value' => $normalized, 'error' => '');
    }

    private function validateRequiredUrl($rawUrl)
    {
        $trimmed = trim(wp_unslash((string) $rawUrl));

        if ($trimmed === '') {
            return array('value' => '', 'error' => 'invalid_feed_url');
        }

        $normalized = $this->normalizeUrl($trimmed);

        if ($normalized === '') {
            return array('value' => '', 'error' => 'invalid_feed_url');
        }

        return array('value' => $normalized, 'error' => '');
    }

    private function validateParserProfile($rawProfile)
    {
        $profile = sanitize_key(wp_unslash((string) $rawProfile));

        if ($profile === '') {
            return array('value' => null, 'error' => '');
        }

        if (strlen($profile) > 64) {
            return array('value' => '', 'error' => 'validation');
        }

        return array('value' => $profile, 'error' => '');
    }

    private function validateAllowedDomains($rawDomains)
    {
        $text = wp_unslash((string) $rawDomains);
        $lines = preg_split('/\R/', $text);
        $normalized = array();
        $seen = array();

        if (!is_array($lines)) {
            $lines = array();
        }

        foreach ($lines as $line) {
            $domain = $this->normalizeDomainLine($line);

            if ($domain === '') {
                continue;
            }

            if (strpos($domain, '*') !== false) {
                return array('value' => '', 'error' => 'invalid_domain');
            }

            if (!$this->isValidHostname($domain)) {
                return array('value' => '', 'error' => 'invalid_domain');
            }

            if (isset($seen[$domain])) {
                continue;
            }

            $seen[$domain] = true;
            $normalized[] = $domain;
        }

        $json = wp_json_encode($normalized);

        if (!is_string($json)) {
            return array('value' => '[]', 'error' => '');
        }

        return array('value' => $json, 'error' => '');
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
}
