<?php

namespace StudyMentor\ContentEngine\Admin;

use StudyMentor\ContentEngine\Data\SourceRepository;
use StudyMentor\ContentEngine\Http\SafeFeedFetcher;

defined('ABSPATH') || exit;

final class BulkConnectivityAuditService
{
    private const CLASSIFICATION_PREFIX_BYTES = 16384;
    private const MAX_RESULT_RESPONSE_BYTES = 131073;
    private const MAX_RESULT_ELAPSED_MS = 2147483647;
    private const RESULT_CODES = array(
        'reachable_rss',
        'reachable_atom',
        'reachable_html',
        'reachable_other',
        'unauthorized_401',
        'forbidden_403',
        'not_found_404',
        'rate_limited_429',
        'server_error',
        'http_error',
        'timeout',
        'dns_failure',
        'tls_failure',
        'unsafe_url',
        'unsafe_redirect',
        'redirect_domain_blocked',
        'redirect_limit_exceeded',
        'response_too_large',
        'invalid_source_configuration',
        'network_error',
    );
    private const FETCH_ERROR_CODES = array(
        'unauthorized_401',
        'forbidden_403',
        'not_found_404',
        'rate_limited_429',
        'server_error',
        'http_error',
        'timeout',
        'dns_failure',
        'tls_failure',
        'unsafe_url',
        'unsafe_redirect',
        'redirect_domain_blocked',
        'redirect_limit_exceeded',
        'response_too_large',
        'network_error',
    );

    private $repository;
    private $fetcher;

    public function __construct(
        SourceRepository $repository,
        SafeFeedFetcher $fetcher
    ) {
        $this->repository = $repository;
        $this->fetcher = $fetcher;
    }

    /**
     * @param array<int, int> $sourceIds
     * @return array<int, array<string, mixed>>
     */
    public function audit(array $sourceIds): array
    {
        $rows = array();

        foreach ($sourceIds as $sourceId) {
            $id = (int) $sourceId;

            try {
                $source = $this->repository->findById($id);
            } catch (\Throwable $throwable) {
                $source = null;
            }

            if (!is_array($source)) {
                $rows[] = $this->invalidConfigurationRow($id);
                continue;
            }

            $configuration = $this->readStoredConfiguration($source, $id);

            if ($configuration === null) {
                $rows[] = $this->invalidConfigurationRow($id, $source);
                continue;
            }

            try {
                $fetchResult = $this->fetcher->fetchForConnectivityAudit(
                    $configuration['feed_url'],
                    $configuration['allowed_domains']
                );
            } catch (\Throwable $throwable) {
                $fetchResult = array(
                    'success' => false,
                    'result_code' => 'network_error',
                );
            }

            $rows[] = $this->buildResultRow($configuration, $fetchResult);
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $source
     * @return array<string, mixed>|null
     */
    private function readStoredConfiguration(array $source, $expectedId)
    {
        if (!isset($source['id']) || (int) $source['id'] !== $expectedId || $expectedId <= 0) {
            return null;
        }

        if (!isset($source['feed_url']) || !is_string($source['feed_url'])) {
            return null;
        }

        $feedUrl = trim($source['feed_url']);

        if ($feedUrl === '' || strlen($feedUrl) > 2048) {
            return null;
        }

        $parts = wp_parse_url($feedUrl);

        if (
            !is_array($parts)
            || !isset($parts['scheme'], $parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
        ) {
            return null;
        }

        $scheme = strtolower((string) $parts['scheme']);
        $host = strtolower(rtrim((string) $parts['host'], '.'));

        if (
            ($scheme !== 'http' && $scheme !== 'https')
            || !$this->isValidHostname($host)
        ) {
            return null;
        }

        if (!isset($source['allowed_domains']) || !is_string($source['allowed_domains'])) {
            return null;
        }

        $allowedDomains = $this->decodeAllowedDomains($source['allowed_domains']);

        if ($allowedDomains === array() || !in_array($host, $allowedDomains, true)) {
            return null;
        }

        return array(
            'source_id' => $expectedId,
            'name' => $this->boundedText(isset($source['name']) ? $source['name'] : '', 200),
            'source_type' => $this->boundedSourceType(
                isset($source['source_type']) ? $source['source_type'] : ''
            ),
            'display_host' => $this->buildSafeDisplayHost($host),
            'feed_url' => $feedUrl,
            'allowed_domains' => $allowedDomains,
        );
    }

    /**
     * @return array<int, string>
     */
    private function decodeAllowedDomains($storedValue)
    {
        $decoded = json_decode($storedValue, true);

        if (!is_array($decoded) || $decoded === array() || count($decoded) > 50) {
            return array();
        }

        $domains = array();
        $expectedIndex = 0;

        foreach ($decoded as $index => $domain) {
            if ($index !== $expectedIndex || !is_string($domain)) {
                return array();
            }

            $normalized = strtolower(trim($domain));

            if (
                $normalized === ''
                || strlen($normalized) > 253
                || !$this->isValidHostname($normalized)
            ) {
                return array();
            }

            $domains[$normalized] = $normalized;
            $expectedIndex++;
        }

        return array_values($domains);
    }

    private function isValidHostname($host)
    {
        $ipHost = trim((string) $host, '[]');

        if (filter_var($ipHost, FILTER_VALIDATE_IP) !== false) {
            return $host === $ipHost || $host === '[' . $ipHost . ']';
        }

        return filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false;
    }

    private function buildSafeDisplayHost($host): string
    {
        if (!is_scalar($host)) {
            return '';
        }

        $normalized = strtolower(rtrim(trim((string) $host), '.'));

        if ($normalized === '') {
            return '';
        }

        $normalized = $this->boundedText($normalized, 253);
        $ipCandidate = $normalized;

        if (
            strlen($ipCandidate) >= 2
            && $ipCandidate[0] === '['
            && substr($ipCandidate, -1) === ']'
        ) {
            $ipCandidate = substr($ipCandidate, 1, -1);
        }

        if (filter_var($ipCandidate, FILTER_VALIDATE_IP) !== false) {
            return '';
        }

        if (
            filter_var(
                $normalized,
                FILTER_VALIDATE_DOMAIN,
                FILTER_FLAG_HOSTNAME
            ) === false
        ) {
            return '';
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $configuration
     * @param array<string, mixed> $fetchResult
     * @return array<string, mixed>
     */
    private function buildResultRow(array $configuration, array $fetchResult)
    {
        $success = isset($fetchResult['success']) && $fetchResult['success'] === true;
        $truncated = isset($fetchResult['truncated']) && $fetchResult['truncated'] === true;
        $resultCode = 'network_error';

        if ($success) {
            $body = isset($fetchResult['body']) && is_string($fetchResult['body'])
                ? $fetchResult['body']
                : '';
            $prefix = substr($body, 0, self::CLASSIFICATION_PREFIX_BYTES);
            $body = '';
            $fetchResult['body'] = '';
            $resultCode = $this->classifyPrefix($prefix);
            $prefix = '';
        } else {
            $candidate = isset($fetchResult['result_code'])
                ? (string) $fetchResult['result_code']
                : '';

            if ($candidate === 'response_too_large' && $truncated) {
                $prefix = isset($fetchResult['truncated_prefix'])
                    && is_string($fetchResult['truncated_prefix'])
                    ? substr($fetchResult['truncated_prefix'], 0, self::CLASSIFICATION_PREFIX_BYTES)
                    : '';
                $fetchResult['truncated_prefix'] = '';
                $classified = $this->classifyPrefix($prefix);
                $prefix = '';
                $resultCode = in_array(
                    $classified,
                    array('reachable_rss', 'reachable_atom', 'reachable_html'),
                    true
                )
                    ? $classified
                    : 'response_too_large';
            } elseif (in_array($candidate, self::FETCH_ERROR_CODES, true)) {
                $resultCode = $candidate;
            }
        }

        if (!in_array($resultCode, self::RESULT_CODES, true)) {
            $resultCode = 'network_error';
        }

        return array(
            'source_id' => (int) $configuration['source_id'],
            'name' => (string) $configuration['name'],
            'source_type' => (string) $configuration['source_type'],
            'host' => (string) $configuration['display_host'],
            'result_code' => $resultCode,
            'http_status' => $this->boundedInteger(
                isset($fetchResult['http_status']) ? $fetchResult['http_status'] : 0,
                999
            ),
            'content_type' => $this->normalizeContentType(
                isset($fetchResult['content_type']) ? $fetchResult['content_type'] : ''
            ),
            'response_bytes' => $this->boundedInteger(
                isset($fetchResult['response_bytes']) ? $fetchResult['response_bytes'] : 0,
                self::MAX_RESULT_RESPONSE_BYTES
            ),
            'elapsed_ms' => $this->boundedInteger(
                isset($fetchResult['elapsed_ms']) ? $fetchResult['elapsed_ms'] : 0,
                self::MAX_RESULT_ELAPSED_MS
            ),
            'redirect_count' => $this->boundedInteger(
                isset($fetchResult['redirect_count']) ? $fetchResult['redirect_count'] : 0,
                2
            ),
            'truncated' => $truncated,
        );
    }

    private function classifyPrefix($prefix)
    {
        if (strncmp($prefix, "\xEF\xBB\xBF", 3) === 0) {
            $prefix = substr($prefix, 3);
        }

        $prefix = ltrim($prefix, " \t\r\n\f\v");
        $rootCandidate = $prefix;

        if (
            preg_match('/^<\?xml\s+[^>]*\?>\s*/i', $prefix, $matches) === 1
            && isset($matches[0])
        ) {
            $rootCandidate = substr($prefix, strlen($matches[0]));
        }

        if (preg_match('/^<rss(?:\s|>)/i', $rootCandidate) === 1) {
            return 'reachable_rss';
        }

        if (preg_match('/^<feed(?:\s|>)/i', $rootCandidate) === 1) {
            return 'reachable_atom';
        }

        if (
            preg_match('/^<rdf:RDF(?:\s|>)[^>]*>/i', $rootCandidate, $rootMatch) === 1
            && isset($rootMatch[0])
            && preg_match(
                '/\bxmlns:rdf\s*=\s*["\']http:\/\/www\.w3\.org\/1999\/02\/22-rdf-syntax-ns#["\']/i',
                $rootMatch[0]
            ) === 1
            && preg_match(
                '/\bxmlns\s*=\s*["\']http:\/\/purl\.org\/rss\/1\.0\/["\']/i',
                $rootMatch[0]
            ) === 1
        ) {
            return 'reachable_rss';
        }

        if (
            stripos($prefix, '<!doctype html') === 0
            || preg_match('/^<html(?:\s|>)/i', $prefix) === 1
        ) {
            return 'reachable_html';
        }

        return 'reachable_other';
    }

    private function normalizeContentType($contentType)
    {
        if (!is_string($contentType)) {
            return '';
        }

        $mimeType = strtolower(trim(strtok($contentType, ';')));

        if (
            $mimeType === ''
            || strlen($mimeType) > 100
            || preg_match('~^[a-z0-9!#$&^_.+-]+/[a-z0-9!#$&^_.+-]+$~', $mimeType) !== 1
        ) {
            return '';
        }

        return $mimeType;
    }

    private function boundedInteger($value, $maximum)
    {
        if (!is_int($value) && !is_float($value) && !is_string($value)) {
            return 0;
        }

        $integer = (int) $value;

        if ($integer < 0) {
            return 0;
        }

        return $integer > $maximum ? $maximum : $integer;
    }

    private function boundedText($value, $maximumCharacters)
    {
        $text = sanitize_text_field(is_scalar($value) ? (string) $value : '');

        if (function_exists('mb_substr')) {
            return mb_substr($text, 0, $maximumCharacters, 'UTF-8');
        }

        return substr($text, 0, $maximumCharacters);
    }

    private function boundedSourceType($value)
    {
        $sourceType = strtolower(is_scalar($value) ? (string) $value : '');
        $sourceType = preg_replace('/[^a-z0-9_-]/', '', $sourceType);

        return is_string($sourceType) ? substr($sourceType, 0, 30) : '';
    }

    /**
     * @param array<string, mixed> $source
     * @return array<string, mixed>
     */
    private function invalidConfigurationRow($sourceId, array $source = array())
    {
        return array(
            'source_id' => $sourceId > 0 ? (int) $sourceId : 0,
            'name' => $this->boundedText(isset($source['name']) ? $source['name'] : '', 200),
            'source_type' => $this->boundedSourceType(
                isset($source['source_type']) ? $source['source_type'] : ''
            ),
            'host' => '',
            'result_code' => 'invalid_source_configuration',
            'http_status' => 0,
            'content_type' => '',
            'response_bytes' => 0,
            'elapsed_ms' => 0,
            'redirect_count' => 0,
            'truncated' => false,
        );
    }
}
