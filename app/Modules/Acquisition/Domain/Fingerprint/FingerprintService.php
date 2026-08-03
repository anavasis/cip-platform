<?php

namespace App\Modules\Acquisition\Domain\Fingerprint;

final class FingerprintService
{
    /** @return array<string, string> */
    public function describe(): array
    {
        return [
            'algorithm' => 'sha256',
            'body_hash' => 'raw body',
            'content_hash' => 'normalized body (trimmed, CRLF to LF)',
            'identity_hash' => 'source_key|normalized_url',
            'url_normalization' => 'trim, lowercase',
        ];
    }

    /** @return array{body_hash: string, content_hash: string, identity_hash: string} */
    public function fingerprint(string $body, string $url, string $sourceKey = ''): array
    {
        $normalizedBody = trim(str_replace(["\r\n", "\r"], "\n", $body));
        $normalizedUrl = strtolower(trim($url));

        return [
            'body_hash' => hash('sha256', $body),
            'content_hash' => hash('sha256', $normalizedBody),
            'identity_hash' => hash('sha256', strtolower(trim($sourceKey)).'|'.$normalizedUrl),
        ];
    }
}
