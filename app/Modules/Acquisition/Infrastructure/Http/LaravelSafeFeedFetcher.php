<?php

namespace App\Modules\Acquisition\Infrastructure\Http;

final class LaravelSafeFeedFetcher implements FeedFetcherInterface
{
    private const TIMEOUT_SECONDS = 8;

    private const MAX_REDIRECT_HOPS = 3;

    private const MAX_BODY_BYTES = 2097152;

    private const AUDIT_TIMEOUT_SECONDS = 3;

    private const AUDIT_MAX_REDIRECT_HOPS = 2;

    private const AUDIT_MAX_BODY_BYTES = 131072;

    private const AUDIT_CLASSIFICATION_PREFIX_BYTES = 16384;

    private const ALLOWED_REDIRECT_STATUSES = [301, 302, 303, 307, 308];

    private const USER_AGENT = 'CIP-Acquisition/1.0 (+https://cip.anavasis.tech)';

    private const ACCEPT_HEADER = 'application/rss+xml, application/atom+xml, application/xml, text/xml;q=0.9, */*;q=0.8';

    private const AUDIT_ACCEPT_HEADER = 'application/rss+xml, application/atom+xml, text/html;q=0.9, application/xml;q=0.8, text/xml;q=0.8, */*;q=0.5';

    public function __construct(
        private readonly SafeUrlGuard $urlGuard,
        private readonly CurlPinnedHttpTransport $transport = new CurlPinnedHttpTransport,
    ) {}

    public function fetch(string $url, array $allowedDomains): array
    {
        $requestedUrl = trim($url);
        $validation = $this->urlGuard->validate($requestedUrl, $allowedDomains);

        if ($validation['ok'] !== true) {
            return $this->errorResult('url_blocked', '', '', 0, '', 0);
        }

        $currentUrl = $validation['url'];
        $currentIps = $validation['ips'];
        $redirectCount = 0;

        while (true) {
            $response = $this->performRequest(
                $currentUrl,
                $currentIps,
                self::TIMEOUT_SECONDS,
                self::MAX_BODY_BYTES,
                self::ACCEPT_HEADER,
            );

            if ($response['transport_error'] !== '') {
                return $this->errorResult(
                    'transport_error',
                    $requestedUrl,
                    $currentUrl,
                    0,
                    '',
                    0,
                );
            }

            $statusCode = $response['status_code'];

            if (in_array($statusCode, self::ALLOWED_REDIRECT_STATUSES, true)) {
                if ($redirectCount >= self::MAX_REDIRECT_HOPS) {
                    return $this->errorResult(
                        'too_many_redirects',
                        $requestedUrl,
                        $currentUrl,
                        $statusCode,
                        $response['content_type'],
                        0,
                    );
                }

                if ($response['location'] === '') {
                    return $this->errorResult(
                        'invalid_redirect',
                        $requestedUrl,
                        $currentUrl,
                        $statusCode,
                        $response['content_type'],
                        0,
                    );
                }

                $resolvedLocation = $this->resolveRedirectLocation($currentUrl, $response['location']);

                if ($resolvedLocation === '') {
                    return $this->errorResult(
                        'invalid_redirect',
                        $requestedUrl,
                        $currentUrl,
                        $statusCode,
                        $response['content_type'],
                        0,
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
                        0,
                    );
                }

                $currentUrl = $redirectValidation['url'];
                $currentIps = $redirectValidation['ips'];
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
                    $response['body_size'],
                );
            }

            if ($response['body_too_large']) {
                return $this->errorResult(
                    'response_too_large',
                    $requestedUrl,
                    $currentUrl,
                    $statusCode,
                    $response['content_type'],
                    $response['body_size'],
                );
            }

            return [
                'success' => true,
                'error_code' => '',
                'requested_url' => $requestedUrl,
                'final_url' => $currentUrl,
                'http_status' => $statusCode,
                'content_type' => $response['content_type'],
                'response_size' => $response['body_size'],
                'body' => $response['body'],
            ];
        }
    }

    public function fetchForConnectivityAudit(string $url, array $allowedDomains): array
    {
        $startedAt = microtime(true);
        $requestedUrl = trim($url);

        try {
            $validation = $this->urlGuard->validate($requestedUrl, $allowedDomains);
        } catch (\Throwable) {
            return $this->auditErrorResult('network_error', 0, '', 0, $startedAt, 0);
        }

        if ($validation['ok'] !== true) {
            $resultCode = $validation['error'] === 'dns_resolution_failed' ? 'dns_failure' : 'unsafe_url';

            return $this->auditErrorResult($resultCode, 0, '', 0, $startedAt, 0);
        }

        $currentUrl = $validation['url'];
        $currentIps = $validation['ips'];
        $redirectCount = 0;

        while (true) {
            $response = $this->performRequest(
                $currentUrl,
                $currentIps,
                self::AUDIT_TIMEOUT_SECONDS,
                self::AUDIT_MAX_BODY_BYTES,
                self::AUDIT_ACCEPT_HEADER,
                self::AUDIT_CLASSIFICATION_PREFIX_BYTES,
            );

            if ($response['transport_error'] !== '') {
                return $this->auditErrorResult(
                    $this->mapAuditTransportError($response['transport_error']),
                    0,
                    '',
                    0,
                    $startedAt,
                    $redirectCount,
                );
            }

            $statusCode = $response['status_code'];

            if (in_array($statusCode, self::ALLOWED_REDIRECT_STATUSES, true)) {
                if ($redirectCount >= self::AUDIT_MAX_REDIRECT_HOPS) {
                    return $this->auditErrorResult(
                        'redirect_limit_exceeded',
                        $statusCode,
                        $response['content_type'],
                        0,
                        $startedAt,
                        $redirectCount,
                    );
                }

                $resolvedLocation = $response['location'] !== ''
                    ? $this->resolveAuditRedirectLocation($currentUrl, $response['location'])
                    : '';

                if ($resolvedLocation === '') {
                    return $this->auditErrorResult(
                        'unsafe_redirect',
                        $statusCode,
                        $response['content_type'],
                        0,
                        $startedAt,
                        $redirectCount,
                    );
                }

                try {
                    $redirectValidation = $this->urlGuard->validate($resolvedLocation, $allowedDomains);
                } catch (\Throwable) {
                    return $this->auditErrorResult(
                        'network_error',
                        $statusCode,
                        $response['content_type'],
                        0,
                        $startedAt,
                        $redirectCount,
                    );
                }

                if ($redirectValidation['ok'] !== true) {
                    $resultCode = match ($redirectValidation['error']) {
                        'host_not_allowed' => 'redirect_domain_blocked',
                        'dns_resolution_failed' => 'network_error',
                        default => 'unsafe_redirect',
                    };

                    return $this->auditErrorResult(
                        $resultCode,
                        $statusCode,
                        $response['content_type'],
                        0,
                        $startedAt,
                        $redirectCount,
                    );
                }

                $currentUrl = $redirectValidation['url'];
                $currentIps = $redirectValidation['ips'];
                $redirectCount++;

                continue;
            }

            if ($statusCode < 200 || $statusCode >= 300) {
                return $this->auditErrorResult(
                    $this->mapAuditHttpStatus($statusCode),
                    $statusCode,
                    $response['content_type'],
                    $response['body_size'],
                    $startedAt,
                    $redirectCount,
                );
            }

            if ($response['body_too_large']) {
                return [
                    'success' => false,
                    'result_code' => 'response_too_large',
                    'http_status' => max(0, min(999, $statusCode)),
                    'content_type' => $this->normalizeAuditContentType($response['content_type']),
                    'response_bytes' => self::AUDIT_MAX_BODY_BYTES + 1,
                    'elapsed_ms' => $this->auditElapsedMilliseconds($startedAt),
                    'redirect_count' => max(0, min(self::AUDIT_MAX_REDIRECT_HOPS, $redirectCount)),
                    'truncated' => true,
                    'truncated_prefix' => $response['truncated_prefix'],
                    'body' => '',
                ];
            }

            return [
                'success' => true,
                'result_code' => '',
                'http_status' => $statusCode,
                'content_type' => $this->normalizeAuditContentType($response['content_type']),
                'response_bytes' => $response['body_size'],
                'elapsed_ms' => $this->auditElapsedMilliseconds($startedAt),
                'redirect_count' => $redirectCount,
                'truncated' => false,
                'body' => $response['body'],
            ];
        }
    }

    /**
     * @return array{
     *   transport_error: string,
     *   status_code: int,
     *   content_type: string,
     *   location: string,
     *   body: string,
     *   truncated_prefix: string,
     *   body_size: int,
     *   body_too_large: bool
     * }
     */
    private function performRequest(
        string $url,
        array $validatedIps,
        int $timeout,
        int $maxBodyBytes,
        string $accept,
        int $classificationPrefixBytes = 0,
    ): array {
        $connection = $this->transport->pinnedConnection($url, $validatedIps);

        if ($connection === null) {
            return [
                'transport_error' => 'validated_address_missing',
                'status_code' => 0,
                'content_type' => '',
                'location' => '',
                'body' => '',
                'truncated_prefix' => '',
                'body_size' => 0,
                'body_too_large' => false,
            ];
        }

        return $this->transport->get($url, $validatedIps, [
            'timeout' => $timeout,
            'max_body_bytes' => $maxBodyBytes,
            'accept' => $accept,
            'user_agent' => self::USER_AGENT,
            'host_header' => $connection['host_header'],
            'resolve' => $connection['resolve'],
            'classification_prefix_bytes' => $classificationPrefixBytes,
        ]);
    }

    private function resolveRedirectLocation(string $currentUrl, string $location): string
    {
        $location = trim($location);

        if ($location === '') {
            return '';
        }

        if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $location) === 1) {
            return $this->sanitizeRedirectUrl($location);
        }

        $currentParts = parse_url($currentUrl);

        if (! is_array($currentParts) || ! isset($currentParts['scheme'], $currentParts['host'])) {
            return '';
        }

        $scheme = strtolower((string) $currentParts['scheme']);
        $host = $this->formatUrlHost((string) $currentParts['host']);
        $port = isset($currentParts['port']) ? ':'.(int) $currentParts['port'] : '';

        if (str_starts_with($location, '//')) {
            return $this->sanitizeRedirectUrl($scheme.':'.$location);
        }

        if (str_starts_with($location, '/')) {
            return $this->sanitizeRedirectUrl($scheme.'://'.$host.$port.$location);
        }

        $basePath = isset($currentParts['path']) ? (string) $currentParts['path'] : '/';

        if ($basePath === '') {
            $basePath = '/';
        }

        if (! str_ends_with($basePath, '/')) {
            $basePath = preg_replace('#/[^/]*$#', '/', $basePath) ?: '/';
        }

        return $this->sanitizeRedirectUrl($scheme.'://'.$host.$port.$basePath.$location);
    }

    private function sanitizeRedirectUrl(string $url): string
    {
        $parsed = parse_url($url);

        if (! is_array($parsed) || ! isset($parsed['scheme'], $parsed['host'])
            || isset($parsed['user']) || isset($parsed['pass'])) {
            return '';
        }

        $scheme = strtolower((string) $parsed['scheme']);

        if ($scheme !== 'http' && $scheme !== 'https') {
            return '';
        }

        $normalized = $scheme.'://'.$this->formatUrlHost(strtolower((string) $parsed['host']));

        if (isset($parsed['port'])) {
            $normalized .= ':'.(int) $parsed['port'];
        }

        return $normalized
            .(isset($parsed['path']) ? (string) $parsed['path'] : '')
            .(isset($parsed['query']) ? '?'.$parsed['query'] : '');
    }

    private function resolveAuditRedirectLocation(string $currentUrl, string $location): string
    {
        $location = trim($location);

        if ($location === '' || strlen($location) > 2048
            || preg_match('/[\x00-\x20\x7F\\\\]/', $location) === 1) {
            return '';
        }

        $fragmentPosition = strpos($location, '#');

        if ($fragmentPosition !== false) {
            $location = substr($location, 0, $fragmentPosition);
        }

        if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $location) === 1) {
            return $this->sanitizeAuditRedirectUrl($location);
        }

        $currentParts = parse_url($currentUrl);

        if (! is_array($currentParts) || ! isset($currentParts['scheme'], $currentParts['host'])) {
            return '';
        }

        $origin = $this->buildAuditOrigin($currentParts);

        if ($origin === '') {
            return '';
        }

        if (str_starts_with($location, '//')) {
            return $this->sanitizeAuditRedirectUrl(strtolower((string) $currentParts['scheme']).':'.$location);
        }

        $currentPath = isset($currentParts['path']) && $currentParts['path'] !== ''
            ? (string) $currentParts['path']
            : '/';

        if ($location === '') {
            return $this->sanitizeAuditRedirectUrl($origin.$currentPath);
        }

        if ($location[0] === '?') {
            return $this->sanitizeAuditRedirectUrl($origin.$currentPath.$location);
        }

        $query = '';
        $queryPosition = strpos($location, '?');

        if ($queryPosition !== false) {
            $query = substr($location, $queryPosition);
            $location = substr($location, 0, $queryPosition);
        }

        if (str_starts_with($location, '/')) {
            $path = $location;
        } else {
            $lastSlash = strrpos($currentPath, '/');
            $basePath = $lastSlash === false ? '/' : substr($currentPath, 0, $lastSlash + 1);
            $path = $basePath.$location;
        }

        return $this->sanitizeAuditRedirectUrl($origin.$this->removeAuditDotSegments($path).$query);
    }

    /** @param array<string, mixed> $parts */
    private function buildAuditOrigin(array $parts): string
    {
        $scheme = strtolower((string) $parts['scheme']);

        if (! in_array($scheme, ['http', 'https'], true)
            || (string) $parts['host'] === ''
            || isset($parts['user'])
            || isset($parts['pass'])) {
            return '';
        }

        $origin = $scheme.'://'.$this->formatUrlHost((string) $parts['host']);

        if (isset($parts['port'])) {
            $port = (int) $parts['port'];

            if ($port < 1 || $port > 65535) {
                return '';
            }

            $origin .= ':'.$port;
        }

        return $origin;
    }

    private function sanitizeAuditRedirectUrl(string $url): string
    {
        if (strlen($url) > 2048 || preg_match('/[\x00-\x20\x7F\\\\]/', $url) === 1) {
            return '';
        }

        $parts = parse_url($url);

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])
            || isset($parts['user']) || isset($parts['pass'])) {
            return '';
        }

        $origin = $this->buildAuditOrigin($parts);

        if ($origin === '') {
            return '';
        }

        $path = isset($parts['path']) ? (string) $parts['path'] : '';
        $query = isset($parts['query']) ? '?'.(string) $parts['query'] : '';

        return $origin.$this->removeAuditDotSegments($path).$query;
    }

    private function removeAuditDotSegments(string $path): string
    {
        if ($path === '') {
            return '';
        }

        $hasLeadingSlash = $path[0] === '/';
        $hasTrailingSlash = str_ends_with($path, '/');
        $normalized = [];

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                array_pop($normalized);

                continue;
            }

            $normalized[] = $segment;
        }

        $result = ($hasLeadingSlash ? '/' : '').implode('/', $normalized);

        if ($hasTrailingSlash && $result !== '' && ! str_ends_with($result, '/')) {
            $result .= '/';
        }

        return $result === '' && $hasLeadingSlash ? '/' : $result;
    }

    private function formatUrlHost(string $host): string
    {
        if (str_starts_with($host, '[') && str_ends_with($host, ']')) {
            return $host;
        }

        return str_contains($host, ':') ? '['.$host.']' : $host;
    }

    /** @return array<string, mixed> */
    private function errorResult(
        string $errorCode,
        string $requestedUrl,
        string $finalUrl,
        int $statusCode,
        string $contentType,
        int $bodySize,
    ): array {
        return [
            'success' => false,
            'error_code' => $errorCode,
            'requested_url' => $requestedUrl,
            'final_url' => $finalUrl,
            'http_status' => $statusCode,
            'content_type' => $contentType,
            'response_size' => $bodySize,
            'body' => '',
        ];
    }

    private function mapAuditHttpStatus(int $statusCode): string
    {
        return match ($statusCode) {
            401 => 'unauthorized_401',
            403 => 'forbidden_403',
            404 => 'not_found_404',
            429 => 'rate_limited_429',
            default => $statusCode >= 500 && $statusCode <= 599 ? 'server_error' : 'http_error',
        };
    }

    private function mapAuditTransportError(string $message): string
    {
        $normalized = strtolower($message);

        if (str_contains($normalized, 'timed out') || str_contains($normalized, 'curl error 28')) {
            return 'timeout';
        }

        if (str_contains($normalized, 'could not resolve') || str_contains($normalized, 'curl error 6')) {
            return 'dns_failure';
        }

        if (preg_match('/curl error (?:35|51|53|54|58|59|60|64|66|77|80|82|83|90|91)/i', $message) === 1
            || str_contains($normalized, 'ssl')
            || str_contains($normalized, 'tls')) {
            return 'tls_failure';
        }

        return 'network_error';
    }

    private function normalizeAuditContentType(string $contentType): string
    {
        $mimeType = strtolower($this->normalizeContentType($contentType));

        if ($mimeType === '' || strlen($mimeType) > 100
            || preg_match('~^[a-z0-9!#$&^_.+-]+/[a-z0-9!#$&^_.+-]+$~', $mimeType) !== 1) {
            return '';
        }

        return $mimeType;
    }

    private function normalizeContentType(string $contentType): string
    {
        $separator = strpos($contentType, ';');

        return trim($separator === false ? $contentType : substr($contentType, 0, $separator));
    }

    /** @return array<string, mixed> */
    private function auditErrorResult(
        string $resultCode,
        int $statusCode,
        string $contentType,
        int $responseBytes,
        float $startedAt,
        int $redirectCount,
    ): array {
        return [
            'success' => false,
            'result_code' => $resultCode,
            'http_status' => max(0, min(999, $statusCode)),
            'content_type' => $this->normalizeAuditContentType($contentType),
            'response_bytes' => max(0, min(self::AUDIT_MAX_BODY_BYTES + 1, $responseBytes)),
            'elapsed_ms' => $this->auditElapsedMilliseconds($startedAt),
            'redirect_count' => max(0, min(self::AUDIT_MAX_REDIRECT_HOPS, $redirectCount)),
            'truncated' => false,
            'body' => '',
        ];
    }

    private function auditElapsedMilliseconds(float $startedAt): int
    {
        $elapsed = (microtime(true) - $startedAt) * 1000;

        if (! is_finite($elapsed) || $elapsed <= 0) {
            return 0;
        }

        return (int) min(2147483647, round($elapsed));
    }
}
