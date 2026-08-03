<?php

namespace App\Modules\Announcement\Domain;

/**
 * Item-level identity and content fingerprinting.
 */
final class AnnouncementIdentityService
{
    public const IDENTITY_BASIS_CANONICAL_URL = 'canonical_url';

    public function identityHash(string $canonicalUrl): string
    {
        $normalized = $this->normalizeUrl($canonicalUrl);

        if ($normalized === '') {
            return '';
        }

        return hash('sha256', $normalized);
    }

    public function identityBasis(): string
    {
        return self::IDENTITY_BASIS_CANONICAL_URL;
    }

    public function contentHash(AnnouncementCandidate $candidate): string
    {
        $payload = [
            'title' => $this->normalizeText($candidate->title()),
            'canonical_url' => $this->normalizeUrl($candidate->canonicalUrl()),
            'source_guid' => $this->normalizeText($candidate->sourceGuid()),
            'published_at_utc' => $this->normalizeText($candidate->publishedAtUtc()),
        ];

        return hash('sha256', $this->encodePayload($payload));
    }

    public function normalizeUrl(string $url): string
    {
        $trimmed = trim($url);

        if ($trimmed === '') {
            return '';
        }

        $parsed = parse_url($trimmed);

        if (! is_array($parsed) || ! isset($parsed['scheme'], $parsed['host'])) {
            return '';
        }

        $scheme = strtolower((string) $parsed['scheme']);
        $host = strtolower((string) $parsed['host']);

        if ($scheme === '' || $host === '') {
            return '';
        }

        $port = isset($parsed['port']) ? (int) $parsed['port'] : null;

        if (($scheme === 'http' && $port === 80) || ($scheme === 'https' && $port === 443)) {
            $port = null;
        }

        $path = isset($parsed['path']) ? (string) $parsed['path'] : '';
        $path = preg_replace('#/+#', '/', $path) ?? '';

        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }

        return $scheme.'://'.$host
            .($port !== null ? ':'.$port : '')
            .$path
            .(isset($parsed['query']) ? '?'.$parsed['query'] : '');
    }

    private function normalizeText(string $text): string
    {
        return trim(str_replace(["\r\n", "\r"], "\n", $text));
    }

    /**
     * @param  array<string, string>  $payload
     */
    private function encodePayload(array $payload): string
    {
        $encoded = json_encode($payload);

        return is_string($encoded) ? $encoded : '';
    }
}
