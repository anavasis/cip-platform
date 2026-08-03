<?php

namespace Tests\Unit\Modules\Acquisition;

use App\Modules\Acquisition\Infrastructure\Http\SafeUrlGuard;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SafeUrlGuardTest extends TestCase
{
    private SafeUrlGuard $guard;

    protected function setUp(): void
    {
        parent::setUp();

        $this->guard = new SafeUrlGuard;
    }

    public function test_empty_allowlist_fails_closed(): void
    {
        $result = $this->guard->validate('https://example.com/feed', []);

        $this->assertFalse($result['ok']);
        $this->assertSame('allowed_domains_empty', $result['error']);
    }

    public function test_host_not_in_allowlist_fails(): void
    {
        $result = $this->guard->validate('https://untrusted.example/feed', ['example.com']);

        $this->assertFalse($result['ok']);
        $this->assertSame('host_not_allowed', $result['error']);
    }

    public function test_credentials_are_rejected(): void
    {
        $result = $this->guard->validate('https://user:secret@example.com/feed', ['example.com']);

        $this->assertFalse($result['ok']);
        $this->assertSame('credentials_not_allowed', $result['error']);
    }

    #[DataProvider('invalidSchemes')]
    public function test_invalid_schemes_are_rejected(string $url): void
    {
        $result = $this->guard->validate($url, ['example.com']);

        $this->assertFalse($result['ok']);
        $this->assertSame('invalid_scheme', $result['error']);
    }

    /** @return array<string, array{string}> */
    public static function invalidSchemes(): array
    {
        return [
            'file' => ['file://example.com/etc/passwd'],
            'ftp' => ['ftp://example.com/feed'],
            'javascript' => ['javascript://example.com/alert(1)'],
        ];
    }

    #[DataProvider('privateIpv4Addresses')]
    public function test_private_literal_ip_addresses_are_rejected(string $address): void
    {
        $result = $this->guard->validate("http://{$address}/feed", [$address]);

        $this->assertFalse($result['ok']);
        $this->assertSame('non_public_address', $result['error']);
    }

    /** @return array<string, array{string}> */
    public static function privateIpv4Addresses(): array
    {
        return [
            'loopback' => ['127.0.0.1'],
            'ten network' => ['10.22.33.44'],
            'private 192 network' => ['192.168.5.4'],
            'link local' => ['169.254.10.20'],
        ];
    }

    public function test_allowlist_requires_exact_host_match(): void
    {
        $result = $this->guard->validate('https://feeds.example.com/rss', ['example.com']);

        $this->assertFalse($result['ok']);
        $this->assertSame('host_not_allowed', $result['error']);
    }

    public function test_success_returns_the_validated_public_addresses(): void
    {
        $guard = new SafeUrlGuard(
            static fn (string $host): array => $host === 'feeds.example.test'
                ? ['93.184.216.34', '2606:4700:4700::1111']
                : [],
        );

        $result = $guard->validate(
            'https://feeds.example.test/rss',
            ['feeds.example.test'],
        );

        $this->assertTrue($result['ok']);
        $this->assertSame(
            ['93.184.216.34', '2606:4700:4700::1111'],
            $result['ips'],
        );
    }
}
