<?php

namespace StudyMentor\ContentEngine\Fingerprint;

defined('ABSPATH') || exit;

final class FingerprintService
{
    /**
     * @param string $body
     * @param string $url
     * @param string $sourceKey
     * @return array{body_hash: string, content_hash: string, identity_hash: string}
     */
    public function fingerprint($body, $url, $sourceKey = '')
    {
        $rawBody = (string) $body;
        $normalizedBody = $this->normalizeBody($rawBody);
        $normalizedUrl = $this->normalizeUrl((string) $url);

        return array(
            'body_hash' => hash('sha256', $rawBody),
            'content_hash' => hash('sha256', $normalizedBody),
            'identity_hash' => hash(
                'sha256',
                strtolower(trim((string) $sourceKey)) . '|' . $normalizedUrl
            ),
        );
    }

    /**
     * @param string $body
     * @return string
     */
    private function normalizeBody($body)
    {
        $normalized = str_replace(array("\r\n", "\r"), "\n", (string) $body);
        $normalized = trim($normalized);

        return $normalized;
    }

    /**
     * @param string $url
     * @return string
     */
    private function normalizeUrl($url)
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
}
