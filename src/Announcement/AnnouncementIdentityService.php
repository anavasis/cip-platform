<?php

namespace StudyMentor\ContentEngine\Announcement;

defined('ABSPATH') || exit;

/**
 * Item-level announcement identity and content fingerprinting.
 * Distinct from feed-level FingerprintService hashes.
 */
final class AnnouncementIdentityService
{
    public const IDENTITY_BASIS_CANONICAL_URL = 'canonical_url';

    /**
     * @param string $canonicalUrl
     * @return string
     */
    public function identityHash($canonicalUrl)
    {
        $normalized = $this->normalizeUrl((string) $canonicalUrl);

        if ($normalized === '') {
            return '';
        }

        return hash('sha256', $normalized);
    }

    /**
     * @return string
     */
    public function identityBasis()
    {
        return self::IDENTITY_BASIS_CANONICAL_URL;
    }

    /**
     * @param AnnouncementCandidate $candidate
     * @return string
     */
    public function contentHash(AnnouncementCandidate $candidate)
    {
        $payload = array(
            'title' => $this->normalizeText($candidate->title()),
            'canonical_url' => $this->normalizeUrl($candidate->canonicalUrl()),
            'source_guid' => $this->normalizeText($candidate->sourceGuid()),
            'published_at_utc' => $this->normalizeText($candidate->publishedAtUtc()),
        );

        return hash('sha256', $this->encodePayload($payload));
    }

    /**
     * @param string $url
     * @return string
     */
    public function normalizeUrl($url)
    {
        $trimmed = trim((string) $url);

        if ($trimmed === '') {
            return '';
        }

        if (function_exists('esc_url_raw')) {
            $sanitized = esc_url_raw($trimmed);

            if (is_string($sanitized) && $sanitized !== '') {
                $trimmed = $sanitized;
            }
        }

        return strtolower($trimmed);
    }

    /**
     * @param string $text
     * @return string
     */
    private function normalizeText($text)
    {
        $normalized = str_replace(array("\r\n", "\r"), "\n", (string) $text);

        return trim($normalized);
    }

    /**
     * @param array<string, string> $payload
     * @return string
     */
    private function encodePayload(array $payload)
    {
        if (function_exists('wp_json_encode')) {
            $encoded = wp_json_encode($payload);

            if (is_string($encoded) && $encoded !== '') {
                return $encoded;
            }
        }

        $fallback = json_encode($payload);

        return is_string($fallback) ? $fallback : '';
    }
}
