<?php

namespace StudyMentor\ContentEngine\Http;

defined('ABSPATH') || exit;

final class SafeFeedFetcher
{
    private const TIMEOUT_SECONDS = 8;
    private const ALLOWED_REDIRECT_STATUSES = array(301, 302, 303, 307, 308);
    private const MAX_REDIRECT_HOPS = 3;
    private const MAX_BODY_BYTES = 2097152;
    private const LIMIT_RESPONSE_SIZE = 2097153;
    private const USER_AGENT = 'StudyMentor-Content-Engine/0.4.0 FeedPreview';
    private const ACCEPT_HEADER = 'application/rss+xml, application/atom+xml, application/xml, text/xml;q=0.9, */*;q=0.8';

    private $urlGuard;

    public function __construct(SafeUrlGuard $urlGuard)
    {
        $this->urlGuard = $urlGuard;
    }

    /**
     * @param string $feedUrl
     * @param array<int, string> $allowedDomains
     * @return array<string, mixed>
     */
    public function fetch($feedUrl, array $allowedDomains)
    {
        $requestedUrl = trim((string) $feedUrl);
        $validation = $this->urlGuard->validate($requestedUrl, $allowedDomains);

        if ($validation['ok'] !== true) {
            return $this->errorResult('url_blocked', '', '', 0, '', 0);
        }

        $currentUrl = $validation['url'];
        $redirectCount = 0;

        while (true) {
            $response = $this->performRequest($currentUrl);

            if ($response['transport_error'] !== '') {
                return $this->errorResult(
                    'transport_error',
                    $requestedUrl,
                    $currentUrl,
                    0,
                    '',
                    0
                );
            }

            $statusCode = (int) $response['status_code'];

            if (in_array($statusCode, self::ALLOWED_REDIRECT_STATUSES, true)) {
                if ($redirectCount >= self::MAX_REDIRECT_HOPS) {
                    return $this->errorResult(
                        'too_many_redirects',
                        $requestedUrl,
                        $currentUrl,
                        $statusCode,
                        $response['content_type'],
                        0
                    );
                }

                $location = $response['location'];

                if ($location === '') {
                    return $this->errorResult(
                        'invalid_redirect',
                        $requestedUrl,
                        $currentUrl,
                        $statusCode,
                        $response['content_type'],
                        0
                    );
                }

                $resolvedLocation = $this->resolveRedirectLocation($currentUrl, $location);

                if ($resolvedLocation === '') {
                    return $this->errorResult(
                        'invalid_redirect',
                        $requestedUrl,
                        $currentUrl,
                        $statusCode,
                        $response['content_type'],
                        0
                    );
                }

                $redirectValidation = $this->urlGuard->validate($resolvedLocation, $allowedDomains);

                if ($redirectValidation['ok'] !== true) {
                    return $this->errorResult(
                        'redirect_blocked',
                        $requestedUrl,
                        $currentUrl,
                        $statusCode,
                        $response['content_type'],
                        0
                    );
                }

                $currentUrl = $redirectValidation['url'];
                $redirectCount++;

                continue;
            }

            if ($statusCode < 200 || $statusCode >= 300) {
                return $this->errorResult(
                    'http_error',
                    $requestedUrl,
                    $currentUrl,
                    $statusCode,
                    $response['content_type'],
                    (int) $response['body_size']
                );
            }

            if ($response['body_too_large']) {
                return $this->errorResult(
                    'response_too_large',
                    $requestedUrl,
                    $currentUrl,
                    $statusCode,
                    $response['content_type'],
                    (int) $response['body_size']
                );
            }

            return array(
                'success' => true,
                'error_code' => '',
                'requested_url' => $requestedUrl,
                'final_url' => $currentUrl,
                'http_status' => $statusCode,
                'content_type' => $response['content_type'],
                'response_size' => (int) $response['body_size'],
                'body' => $response['body'],
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function performRequest($url)
    {
        $args = array(
            'timeout' => self::TIMEOUT_SECONDS,
            'redirection' => 0,
            'reject_unsafe_urls' => true,
            'sslverify' => true,
            'cookies' => array(),
            'headers' => array(
                'User-Agent' => self::USER_AGENT,
                'Accept' => self::ACCEPT_HEADER,
            ),
            'limit_response_size' => self::LIMIT_RESPONSE_SIZE,
        );

        $response = wp_safe_remote_get($url, $args);

        if (is_wp_error($response)) {
            return array(
                'transport_error' => 'request_failed',
                'status_code' => 0,
                'content_type' => '',
                'location' => '',
                'body' => '',
                'body_size' => 0,
                'body_too_large' => false,
            );
        }

        $statusCode = (int) wp_remote_retrieve_response_code($response);
        $headers = wp_remote_retrieve_headers($response);
        $contentType = '';
        $contentLength = 0;

        if (is_object($headers) && method_exists($headers, 'offsetGet')) {
            $rawContentType = $headers->offsetGet('content-type');

            if (is_string($rawContentType) && $rawContentType !== '') {
                $contentType = trim(strtok($rawContentType, ';'));
            }

            $rawContentLength = $headers->offsetGet('content-length');

            if (is_string($rawContentLength) || is_numeric($rawContentLength)) {
                $contentLength = (int) $rawContentLength;
            }
        }

        if ($contentLength > self::MAX_BODY_BYTES) {
            return array(
                'transport_error' => '',
                'status_code' => $statusCode,
                'content_type' => $contentType,
                'location' => $this->extractLocationHeader($headers),
                'body' => '',
                'body_size' => $contentLength,
                'body_too_large' => true,
            );
        }

        $body = (string) wp_remote_retrieve_body($response);
        $bodySize = strlen($body);
        $bodyTooLarge = $bodySize > self::MAX_BODY_BYTES;

        return array(
            'transport_error' => '',
            'status_code' => $statusCode,
            'content_type' => $contentType,
            'location' => $this->extractLocationHeader($headers),
            'body' => $bodyTooLarge ? '' : $body,
            'body_size' => $bodySize,
            'body_too_large' => $bodyTooLarge,
        );
    }

    /**
     * @param mixed $headers
     */
    private function extractLocationHeader($headers)
    {
        if (!is_object($headers) || !method_exists($headers, 'offsetGet')) {
            return '';
        }

        $location = $headers->offsetGet('location');

        return is_string($location) ? trim($location) : '';
    }

    private function resolveRedirectLocation($currentUrl, $location)
    {
        $location = trim((string) $location);

        if ($location === '') {
            return '';
        }

        if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $location) === 1) {
            return $this->sanitizeRedirectUrl($location);
        }

        $currentParts = wp_parse_url($currentUrl);

        if (!is_array($currentParts) || !isset($currentParts['scheme'], $currentParts['host'])) {
            return '';
        }

        $scheme = strtolower((string) $currentParts['scheme']);
        $host = (string) $currentParts['host'];
        $port = isset($currentParts['port']) ? ':' . (int) $currentParts['port'] : '';

        if (strpos($location, '//') === 0) {
            return $this->sanitizeRedirectUrl($scheme . ':' . $location);
        }

        if (strpos($location, '/') === 0) {
            return $this->sanitizeRedirectUrl($scheme . '://' . $host . $port . $location);
        }

        $basePath = isset($currentParts['path']) ? (string) $currentParts['path'] : '/';

        if ($basePath === '') {
            $basePath = '/';
        }

        if (substr($basePath, -1) !== '/') {
            $basePath = preg_replace('#/[^/]*$#', '/', $basePath);

            if (!is_string($basePath) || $basePath === '') {
                $basePath = '/';
            }
        }

        return $this->sanitizeRedirectUrl($scheme . '://' . $host . $port . $basePath . $location);
    }

    private function sanitizeRedirectUrl($url)
    {
        $parsed = wp_parse_url($url);

        if (!is_array($parsed) || !isset($parsed['scheme'], $parsed['host'])) {
            return '';
        }

        if (isset($parsed['user']) || isset($parsed['pass'])) {
            return '';
        }

        $scheme = strtolower((string) $parsed['scheme']);

        if ($scheme !== 'http' && $scheme !== 'https') {
            return '';
        }

        $host = strtolower((string) $parsed['host']);
        $port = isset($parsed['port']) ? (int) $parsed['port'] : null;
        $path = isset($parsed['path']) ? (string) $parsed['path'] : '';
        $query = isset($parsed['query']) ? '?' . $parsed['query'] : '';
        $normalized = $scheme . '://' . $host;

        if ($port !== null) {
            $normalized .= ':' . $port;
        }

        $normalized .= $path . $query;

        return $normalized;
    }

    /**
     * @return array<string, mixed>
     */
    private function errorResult($errorCode, $requestedUrl, $finalUrl, $statusCode, $contentType, $bodySize)
    {
        return array(
            'success' => false,
            'error_code' => (string) $errorCode,
            'requested_url' => (string) $requestedUrl,
            'final_url' => (string) $finalUrl,
            'http_status' => (int) $statusCode,
            'content_type' => (string) $contentType,
            'response_size' => (int) $bodySize,
            'body' => '',
        );
    }

    private const AUDIT_TIMEOUT_SECONDS = 3;
    private const AUDIT_MAX_REDIRECT_HOPS = 2;
    private const AUDIT_MAX_BODY_BYTES = 131072;
    private const AUDIT_LIMIT_RESPONSE_SIZE = 131073;
    private const AUDIT_CLASSIFICATION_PREFIX_BYTES = 16384;
    private const AUDIT_USER_AGENT = 'StudyMentor-Content-Engine/0.9.0 ConnectivityAudit';
    private const AUDIT_ACCEPT_HEADER = 'application/rss+xml, application/atom+xml, text/html;q=0.9, application/xml;q=0.8, text/xml;q=0.8, */*;q=0.5';

    /**
     * @param string $feedUrl
     * @param array<int, string> $allowedDomains
     * @return array<string, mixed>
     */
    public function fetchForConnectivityAudit(
        $feedUrl,
        array $allowedDomains
    ): array {
        $startedAt = microtime(true);
        $requestedUrl = is_string($feedUrl) ? trim($feedUrl) : '';

        try {
            $validation = $this->urlGuard->validate($requestedUrl, $allowedDomains);
        } catch (\Throwable $throwable) {
            return $this->auditErrorResult('network_error', 0, '', 0, $startedAt, 0);
        }

        if (!is_array($validation) || !isset($validation['ok']) || $validation['ok'] !== true) {
            $guardError = is_array($validation) && isset($validation['error'])
                ? (string) $validation['error']
                : '';
            $resultCode = $guardError === 'dns_resolution_failed'
                ? 'dns_failure'
                : 'unsafe_url';

            return $this->auditErrorResult($resultCode, 0, '', 0, $startedAt, 0);
        }

        $currentUrl = isset($validation['url']) && is_string($validation['url'])
            ? $validation['url']
            : '';

        if ($currentUrl === '') {
            return $this->auditErrorResult('unsafe_url', 0, '', 0, $startedAt, 0);
        }

        $redirectCount = 0;

        while (true) {
            $response = $this->performAuditRequest($currentUrl);

            if ($response['transport_error'] !== '') {
                return $this->auditErrorResult(
                    $response['transport_error'],
                    0,
                    '',
                    0,
                    $startedAt,
                    $redirectCount
                );
            }

            $statusCode = (int) $response['status_code'];

            if (in_array($statusCode, self::ALLOWED_REDIRECT_STATUSES, true)) {
                if ($redirectCount >= self::AUDIT_MAX_REDIRECT_HOPS) {
                    return $this->auditErrorResult(
                        'redirect_limit_exceeded',
                        $statusCode,
                        $response['content_type'],
                        0,
                        $startedAt,
                        $redirectCount
                    );
                }

                if ($response['location'] === '') {
                    return $this->auditErrorResult(
                        'unsafe_redirect',
                        $statusCode,
                        $response['content_type'],
                        0,
                        $startedAt,
                        $redirectCount
                    );
                }

                $resolvedLocation = $this->resolveAuditRedirectLocation(
                    $currentUrl,
                    $response['location']
                );

                if ($resolvedLocation === '') {
                    return $this->auditErrorResult(
                        'unsafe_redirect',
                        $statusCode,
                        $response['content_type'],
                        0,
                        $startedAt,
                        $redirectCount
                    );
                }

                try {
                    $redirectValidation = $this->urlGuard->validate(
                        $resolvedLocation,
                        $allowedDomains
                    );
                } catch (\Throwable $throwable) {
                    return $this->auditErrorResult(
                        'network_error',
                        $statusCode,
                        $response['content_type'],
                        0,
                        $startedAt,
                        $redirectCount
                    );
                }

                if (
                    !is_array($redirectValidation)
                    || !isset($redirectValidation['ok'])
                    || $redirectValidation['ok'] !== true
                ) {
                    $guardError = is_array($redirectValidation)
                        && isset($redirectValidation['error'])
                        ? (string) $redirectValidation['error']
                        : '';
                    $resultCode = $guardError === 'host_not_allowed'
                        ? 'redirect_domain_blocked'
                        : ($guardError === 'dns_resolution_failed'
                            ? 'network_error'
                            : 'unsafe_redirect');

                    return $this->auditErrorResult(
                        $resultCode,
                        $statusCode,
                        $response['content_type'],
                        0,
                        $startedAt,
                        $redirectCount
                    );
                }

                $currentUrl = isset($redirectValidation['url'])
                    && is_string($redirectValidation['url'])
                    ? $redirectValidation['url']
                    : '';

                if ($currentUrl === '') {
                    return $this->auditErrorResult(
                        'unsafe_redirect',
                        $statusCode,
                        $response['content_type'],
                        0,
                        $startedAt,
                        $redirectCount
                    );
                }

                $redirectCount++;
                continue;
            }

            if ($statusCode < 200 || $statusCode >= 300) {
                return $this->auditErrorResult(
                    $this->mapAuditHttpStatus($statusCode),
                    $statusCode,
                    $response['content_type'],
                    (int) $response['body_size'],
                    $startedAt,
                    $redirectCount
                );
            }

            if ($response['body_too_large'] === true) {
                return array(
                    'success' => false,
                    'result_code' => 'response_too_large',
                    'http_status' => max(0, min(999, (int) $statusCode)),
                    'content_type' => $response['content_type'],
                    'response_bytes' => max(
                        0,
                        min(self::AUDIT_LIMIT_RESPONSE_SIZE, (int) $response['body_size'])
                    ),
                    'elapsed_ms' => $this->auditElapsedMilliseconds($startedAt),
                    'redirect_count' => max(
                        0,
                        min(self::AUDIT_MAX_REDIRECT_HOPS, (int) $redirectCount)
                    ),
                    'truncated' => true,
                    'truncated_prefix' => isset($response['truncated_prefix'])
                        ? (string) $response['truncated_prefix']
                        : '',
                    'body' => '',
                );
            }

            return array(
                'success' => true,
                'result_code' => '',
                'http_status' => $statusCode,
                'content_type' => $response['content_type'],
                'response_bytes' => (int) $response['body_size'],
                'elapsed_ms' => $this->auditElapsedMilliseconds($startedAt),
                'redirect_count' => $redirectCount,
                'truncated' => false,
                'body' => $response['body'],
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function performAuditRequest($url)
    {
        $args = array(
            'timeout' => self::AUDIT_TIMEOUT_SECONDS,
            'redirection' => 0,
            'reject_unsafe_urls' => true,
            'sslverify' => true,
            'cookies' => array(),
            'headers' => array(
                'User-Agent' => self::AUDIT_USER_AGENT,
                'Accept' => self::AUDIT_ACCEPT_HEADER,
            ),
            'limit_response_size' => self::AUDIT_LIMIT_RESPONSE_SIZE,
        );

        try {
            $response = wp_safe_remote_get($url, $args);
        } catch (\Throwable $throwable) {
            return $this->auditTransportFailure('network_error');
        }

        if (is_wp_error($response)) {
            return $this->auditTransportFailure($this->mapAuditTransportError($response));
        }

        $statusCode = (int) wp_remote_retrieve_response_code($response);
        $headers = wp_remote_retrieve_headers($response);
        $contentType = $this->normalizeAuditContentType(
            $this->extractAuditHeader($headers, 'content-type')
        );
        $location = $this->extractAuditHeader($headers, 'location');
        $contentLength = $this->parseAuditContentLength(
            $this->extractAuditHeader($headers, 'content-length')
        );

        $body = (string) wp_remote_retrieve_body($response);
        $bodySize = strlen($body);
        $bodyTooLarge = $bodySize > self::AUDIT_MAX_BODY_BYTES
            || $contentLength > self::AUDIT_MAX_BODY_BYTES;

        if ($bodyTooLarge) {
            $truncatedPrefix = substr($body, 0, self::AUDIT_CLASSIFICATION_PREFIX_BYTES);
            $body = '';
            unset($body);

            return array(
                'transport_error' => '',
                'status_code' => $statusCode,
                'content_type' => $contentType,
                'location' => $location,
                'body' => '',
                'truncated_prefix' => $truncatedPrefix,
                'body_size' => self::AUDIT_LIMIT_RESPONSE_SIZE,
                'body_too_large' => true,
            );
        }

        return array(
            'transport_error' => '',
            'status_code' => $statusCode,
            'content_type' => $contentType,
            'location' => $location,
            'body' => $body,
            'truncated_prefix' => '',
            'body_size' => $bodySize,
            'body_too_large' => false,
        );
    }

    private function mapAuditHttpStatus($statusCode)
    {
        if ((int) $statusCode <= 0) {
            return 'network_error';
        }

        switch ((int) $statusCode) {
            case 401:
                return 'unauthorized_401';
            case 403:
                return 'forbidden_403';
            case 404:
                return 'not_found_404';
            case 429:
                return 'rate_limited_429';
            default:
                return $statusCode >= 500 && $statusCode <= 599
                    ? 'server_error'
                    : 'http_error';
        }
    }

    private function mapAuditTransportError($error)
    {
        $errorCode = is_object($error) && method_exists($error, 'get_error_code')
            ? strtolower((string) $error->get_error_code())
            : '';
        $errorMessage = is_object($error) && method_exists($error, 'get_error_message')
            ? (string) $error->get_error_message()
            : '';

        if (in_array($errorCode, array('http_request_timeout', 'request_timeout', 'timeout'), true)) {
            return 'timeout';
        }

        if (in_array($errorCode, array('dns_resolution_failed', 'resolve_host_failed'), true)) {
            return 'dns_failure';
        }

        if (
            in_array(
                $errorCode,
                array('ssl_certificate_error', 'ssl_verification_failed', 'tls_failure'),
                true
            )
        ) {
            return 'tls_failure';
        }

        if (preg_match('/\bcurl error 28\b/i', $errorMessage) === 1) {
            return 'timeout';
        }

        if (preg_match('/\bcurl error 6\b/i', $errorMessage) === 1) {
            return 'dns_failure';
        }

        if (
            preg_match(
                '/\bcurl error (?:35|51|53|54|58|59|60|64|66|77|80|82|83|90|91)\b/i',
                $errorMessage
            ) === 1
        ) {
            return 'tls_failure';
        }

        return 'network_error';
    }

    /**
     * @return array<string, mixed>
     */
    private function auditTransportFailure($resultCode)
    {
        return array(
            'transport_error' => (string) $resultCode,
            'status_code' => 0,
            'content_type' => '',
            'location' => '',
            'body' => '',
            'body_size' => 0,
            'body_too_large' => false,
        );
    }

    private function extractAuditHeader($headers, $name)
    {
        $value = '';

        if (is_object($headers) && method_exists($headers, 'offsetGet')) {
            $value = $headers->offsetGet($name);
        } elseif (is_array($headers)) {
            foreach ($headers as $headerName => $headerValue) {
                if (strtolower((string) $headerName) === $name) {
                    $value = $headerValue;
                    break;
                }
            }
        }

        if (is_array($value)) {
            $value = reset($value);
        }

        if (!is_string($value) && !is_numeric($value)) {
            return '';
        }

        $value = trim((string) $value);

        if (strlen($value) > 2048 || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value) === 1) {
            return '';
        }

        return $value;
    }

    private function parseAuditContentLength($value)
    {
        if (!is_string($value) || preg_match('/^[0-9]+$/', $value) !== 1) {
            return 0;
        }

        $normalized = ltrim($value, '0');

        if ($normalized === '') {
            return 0;
        }

        $maximum = (string) self::AUDIT_MAX_BODY_BYTES;

        if (
            strlen($normalized) > strlen($maximum)
            || (strlen($normalized) === strlen($maximum) && strcmp($normalized, $maximum) > 0)
        ) {
            return self::AUDIT_LIMIT_RESPONSE_SIZE;
        }

        return (int) $normalized;
    }

    private function normalizeAuditContentType($contentType)
    {
        $mimeType = strtolower(trim(strtok((string) $contentType, ';')));

        if (
            $mimeType === ''
            || strlen($mimeType) > 100
            || preg_match('~^[a-z0-9!#$&^_.+-]+/[a-z0-9!#$&^_.+-]+$~', $mimeType) !== 1
        ) {
            return '';
        }

        return $mimeType;
    }

    private function resolveAuditRedirectLocation($currentUrl, $location)
    {
        $location = trim((string) $location);

        if (
            $location === ''
            || strlen($location) > 2048
            || preg_match('/[\x00-\x20\x7F\\\\]/', $location) === 1
        ) {
            return '';
        }

        $fragmentPosition = strpos($location, '#');

        if ($fragmentPosition !== false) {
            $location = substr($location, 0, $fragmentPosition);
        }

        if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $location) === 1) {
            return $this->sanitizeAuditRedirectUrl($location);
        }

        $currentParts = wp_parse_url($currentUrl);

        if (!is_array($currentParts) || !isset($currentParts['scheme'], $currentParts['host'])) {
            return '';
        }

        $origin = $this->buildAuditOrigin($currentParts);

        if ($origin === '') {
            return '';
        }

        if (strpos($location, '//') === 0) {
            return $this->sanitizeAuditRedirectUrl(
                strtolower((string) $currentParts['scheme']) . ':' . $location
            );
        }

        $currentPath = isset($currentParts['path']) && $currentParts['path'] !== ''
            ? (string) $currentParts['path']
            : '/';

        if ($location === '') {
            return $this->sanitizeAuditRedirectUrl($origin . $currentPath);
        }

        if ($location[0] === '?') {
            return $this->sanitizeAuditRedirectUrl($origin . $currentPath . $location);
        }

        $query = '';
        $queryPosition = strpos($location, '?');

        if ($queryPosition !== false) {
            $query = substr($location, $queryPosition);
            $location = substr($location, 0, $queryPosition);
        }

        if (strpos($location, '/') === 0) {
            $path = $location;
        } else {
            $lastSlash = strrpos($currentPath, '/');
            $basePath = $lastSlash === false ? '/' : substr($currentPath, 0, $lastSlash + 1);
            $path = $basePath . $location;
        }

        return $this->sanitizeAuditRedirectUrl(
            $origin . $this->removeAuditDotSegments($path) . $query
        );
    }

    /**
     * @param array<string, mixed> $parts
     */
    private function buildAuditOrigin(array $parts)
    {
        $scheme = strtolower((string) $parts['scheme']);

        if ($scheme !== 'http' && $scheme !== 'https') {
            return '';
        }

        $host = (string) $parts['host'];

        if ($host === '' || isset($parts['user']) || isset($parts['pass'])) {
            return '';
        }

        if (strpos($host, ':') !== false && $host[0] !== '[') {
            $host = '[' . $host . ']';
        }

        $origin = $scheme . '://' . $host;

        if (isset($parts['port'])) {
            $port = (int) $parts['port'];

            if ($port < 1 || $port > 65535) {
                return '';
            }

            $origin .= ':' . $port;
        }

        return $origin;
    }

    private function sanitizeAuditRedirectUrl($url)
    {
        if (
            strlen((string) $url) > 2048
            || preg_match('/[\x00-\x20\x7F\\\\]/', (string) $url) === 1
        ) {
            return '';
        }

        $parts = wp_parse_url($url);

        if (
            !is_array($parts)
            || !isset($parts['scheme'], $parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
        ) {
            return '';
        }

        $origin = $this->buildAuditOrigin($parts);

        if ($origin === '') {
            return '';
        }

        $path = isset($parts['path']) ? (string) $parts['path'] : '';
        $query = isset($parts['query']) ? '?' . (string) $parts['query'] : '';

        return $origin . $this->removeAuditDotSegments($path) . $query;
    }

    private function removeAuditDotSegments($path)
    {
        if ($path === '') {
            return '';
        }

        $hasLeadingSlash = $path[0] === '/';
        $hasTrailingSlash = substr($path, -1) === '/';
        $segments = explode('/', $path);
        $normalized = array();

        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                array_pop($normalized);
                continue;
            }

            $normalized[] = $segment;
        }

        $result = ($hasLeadingSlash ? '/' : '') . implode('/', $normalized);

        if ($hasTrailingSlash && $result !== '' && substr($result, -1) !== '/') {
            $result .= '/';
        }

        return $result === '' && $hasLeadingSlash ? '/' : $result;
    }

    /**
     * @return array<string, mixed>
     */
    private function auditErrorResult(
        $resultCode,
        $statusCode,
        $contentType,
        $responseBytes,
        $startedAt,
        $redirectCount
    ) {
        return array(
            'success' => false,
            'result_code' => (string) $resultCode,
            'http_status' => max(0, min(999, (int) $statusCode)),
            'content_type' => $this->normalizeAuditContentType($contentType),
            'response_bytes' => max(
                0,
                min(self::AUDIT_LIMIT_RESPONSE_SIZE, (int) $responseBytes)
            ),
            'elapsed_ms' => $this->auditElapsedMilliseconds($startedAt),
            'redirect_count' => max(
                0,
                min(self::AUDIT_MAX_REDIRECT_HOPS, (int) $redirectCount)
            ),
            'truncated' => false,
            'body' => '',
        );
    }

    private function auditElapsedMilliseconds($startedAt)
    {
        $elapsed = (microtime(true) - (float) $startedAt) * 1000;

        if (!is_finite($elapsed) || $elapsed <= 0) {
            return 0;
        }

        return (int) min(2147483647, round($elapsed));
    }
}
