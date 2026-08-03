<?php

namespace Tests\Unit\Modules\Acquisition;

use App\Modules\Acquisition\Domain\Fingerprint\FingerprintService;
use PHPUnit\Framework\TestCase;

class FingerprintServiceTest extends TestCase
{
    public function test_fingerprints_are_deterministic_and_normalize_content(): void
    {
        $service = new FingerprintService;
        $first = $service->fingerprint(" line one\r\nline two \r\n", ' HTTPS://Example.COM/feed ', ' Source-A ');
        $second = $service->fingerprint(" line one\r\nline two \r\n", ' HTTPS://Example.COM/feed ', ' Source-A ');

        $this->assertSame($first, $second);
        $this->assertSame(hash('sha256', " line one\r\nline two \r\n"), $first['body_hash']);
        $this->assertSame(hash('sha256', "line one\nline two"), $first['content_hash']);
        $this->assertSame(
            hash('sha256', 'source-a|https://example.com/feed'),
            $first['identity_hash'],
        );
    }

    public function test_describe_returns_hash_contract_metadata(): void
    {
        $description = (new FingerprintService)->describe();

        $this->assertSame('sha256', $description['algorithm']);
        $this->assertSame('raw body', $description['body_hash']);
        $this->assertSame('normalized body (trimmed, CRLF to LF)', $description['content_hash']);
        $this->assertSame('source_key|normalized_url', $description['identity_hash']);
        $this->assertSame('trim, lowercase', $description['url_normalization']);
    }
}
