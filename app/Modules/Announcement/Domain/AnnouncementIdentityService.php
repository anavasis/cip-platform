<?php

namespace App\Modules\Announcement\Domain;

/**
 * Item-level identity and content fingerprinting.
 * Distinct from feed-level FingerprintService hashes.
 *
 * Hash algorithm preserved from editorial-foundation
 * (cc21d03025e138a627a8a2d58e67412da393f7f5).
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
            'source_body' => $this->normalizeSourceBodyForHash($candidate->rawPayload()),
        ];

        return hash('sha256', $this->encodePayload($payload));
    }

    public function normalizeUrl(string $url): string
    {
        $trimmed = trim($url);

        if ($trimmed === '') {
            return '';
        }

        return strtolower($trimmed);
    }

    private function normalizeText(string $text): string
    {
        return trim(str_replace(["\r\n", "\r"], "\n", $text));
    }

    /**
     * @param  array<string, mixed>  $rawPayload
     */
    private function normalizeSourceBodyForHash(array $rawPayload): string
    {
        $body = '';

        foreach (['content', 'description', 'summary'] as $key) {
            if (! isset($rawPayload[$key]) || ! is_scalar($rawPayload[$key])) {
                continue;
            }

            $candidate = trim((string) $rawPayload[$key]);

            if ($candidate !== '') {
                $body = $candidate;
                break;
            }
        }

        if ($body === '') {
            return '';
        }

        $text = html_entity_decode($body, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/<(script|style)\b[^>]*>.*?<\/\1>/is', '', $text) ?? $text;
        $text = strip_tags($text);
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
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
