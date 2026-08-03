<?php

namespace Tests\Feature\Modules\Acquisition;

use App\Modules\Acquisition\Infrastructure\Http\CurlPinnedHttpTransport;
use App\Modules\Acquisition\Infrastructure\Http\LaravelSafeFeedFetcher;
use App\Modules\Acquisition\Infrastructure\Http\SafeUrlGuard;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\Handler\StreamHandler;
use Tests\Feature\Modules\Acquisition\Support\LocalHttpServerFixture;
use Tests\Feature\Modules\Acquisition\Support\LocalPinSafeUrlGuard;
use Tests\TestCase;

class CurlPinnedTransportIntegrationTest extends TestCase
{
    private ?LocalHttpServerFixture $server = null;

    protected function tearDown(): void
    {
        $this->server?->stop();
        $this->server = null;
        parent::tearDown();
    }

    public function test_pinned_public_style_hostname_reaches_local_server_with_original_host(): void
    {
        $this->requireCurl();
        $body = '<?xml version="1.0"?><rss version="2.0"><channel><title>ok</title></channel></rss>';
        $this->server = LocalHttpServerFixture::start([
            '/rss' => [
                'status' => 200,
                'headers' => ['Content-Type' => 'application/rss+xml'],
                'body' => $body,
            ],
        ]);
        $host = 'feeds.pin.test';
        $fetcher = $this->fetcherForLocalPin([$host]);

        $result = $fetcher->fetch(
            'http://'.$host.':'.$this->server->port().'/rss',
            [$host],
        );

        $this->assertTrue($result['success'], (string) ($result['error_code'] ?? ''));
        $this->assertSame($body, $result['body']);
        $requests = $this->server->receivedRequests();
        $this->assertCount(1, $requests);
        $this->assertSame($host.':'.$this->server->port(), $requests[0]['host']);
        $this->assertSame('/rss', $requests[0]['path']);
    }

    public function test_redirect_handling_is_manual_and_unapproved_hostname_is_rejected(): void
    {
        $this->requireCurl();
        $this->server = LocalHttpServerFixture::start([
            '/start' => [
                'status' => 302,
                'headers' => ['Location' => 'http://evil.not-allowed.test/secret'],
                'body' => '',
            ],
        ]);
        $host = 'feeds.pin.test';
        $fetcher = $this->fetcherForLocalPin([$host]);

        $result = $fetcher->fetch(
            'http://'.$host.':'.$this->server->port().'/start',
            [$host],
        );

        $this->assertFalse($result['success']);
        $this->assertSame('redirect_blocked', $result['error_code']);
        $this->assertSame(302, $result['http_status']);
        $this->assertCount(1, $this->server->receivedRequests());
    }

    public function test_redirect_to_literal_loopback_is_rejected_before_follow_connection(): void
    {
        $this->requireCurl();
        $this->server = LocalHttpServerFixture::start([
            '/start' => [
                'status' => 302,
                'headers' => ['Location' => 'http://127.0.0.1/internal'],
                'body' => '',
            ],
            '/internal' => [
                'status' => 200,
                'headers' => ['Content-Type' => 'application/rss+xml'],
                'body' => '<rss/>',
            ],
        ]);
        $host = 'feeds.pin.test';
        $fetcher = $this->fetcherForLocalPin([$host]);

        $result = $fetcher->fetch(
            'http://'.$host.':'.$this->server->port().'/start',
            [$host, '127.0.0.1'],
        );

        $this->assertFalse($result['success']);
        $this->assertSame('redirect_blocked', $result['error_code']);
        $paths = array_column($this->server->receivedRequests(), 'path');
        $this->assertSame(['/start'], $paths);
    }

    public function test_curl_resolve_pinning_is_active_on_real_transport(): void
    {
        $this->requireCurl();
        $this->server = LocalHttpServerFixture::start([
            '/pin' => [
                'status' => 200,
                'headers' => ['Content-Type' => 'application/rss+xml'],
                'body' => '<rss/>',
            ],
        ]);
        $host = 'resolve-proof.pin.test';
        $transport = new CurlPinnedHttpTransport;
        $connection = $transport->pinnedConnection(
            'http://'.$host.':'.$this->server->port().'/pin',
            ['127.0.0.1'],
        );
        $this->assertNotNull($connection);
        $this->assertSame(
            $host.':'.$this->server->port().':127.0.0.1',
            $connection['resolve'],
        );

        $result = $this->fetcherForLocalPin([$host])->fetch(
            'http://'.$host.':'.$this->server->port().'/pin',
            [$host],
        );

        $this->assertTrue($result['success']);
        $this->assertSame($host.':'.$this->server->port(), $this->server->receivedRequests()[0]['host']);
        $client = $transport->createCurlClient();
        $this->assertTrue($transport->usesCurlHandler($client));
    }

    public function test_stream_handler_fallback_is_impossible_on_curl_transport(): void
    {
        $this->requireCurl();
        $transport = new CurlPinnedHttpTransport;
        $client = $transport->createCurlClient();
        $stack = $client->getConfig('handler');
        $reflection = new \ReflectionClass($stack);
        $property = $reflection->getProperty('handler');
        $property->setAccessible(true);
        $handler = $property->getValue($stack);

        $this->assertInstanceOf(CurlHandler::class, $handler);
        $this->assertNotInstanceOf(StreamHandler::class, $handler);
        $this->assertTrue($transport->usesCurlHandler($client));
    }

    public function test_missing_curl_fails_closed_without_stream_fallback(): void
    {
        $transport = new class extends CurlPinnedHttpTransport
        {
            public function assertCurlTransportAvailable(): void
            {
                throw new \RuntimeException('curl_extension_unavailable');
            }
        };
        $guard = new LocalPinSafeUrlGuard(['feeds.pin.test']);
        $result = (new LaravelSafeFeedFetcher($guard, $transport))->fetch(
            'http://feeds.pin.test/rss',
            ['feeds.pin.test'],
        );

        $this->assertFalse($result['success']);
        $this->assertSame('transport_error', $result['error_code']);
    }

    public function test_oversized_plain_response_is_aborted(): void
    {
        $this->requireCurl();
        $oversized = str_repeat('a', 2_097_152 + 64);
        $this->server = LocalHttpServerFixture::start([
            '/big' => [
                'status' => 200,
                'headers' => [
                    'Content-Type' => 'application/rss+xml',
                    'Content-Length' => (string) strlen($oversized),
                ],
                'body' => $oversized,
            ],
        ]);
        $host = 'feeds.pin.test';
        $result = $this->fetcherForLocalPin([$host])->fetch(
            'http://'.$host.':'.$this->server->port().'/big',
            [$host],
        );

        $this->assertFalse($result['success']);
        $this->assertSame('response_too_large', $result['error_code']);
        $this->assertSame('', $result['body']);
    }

    public function test_oversized_gzip_response_is_bounded_without_unbounded_decompression(): void
    {
        $this->requireCurl();
        $plain = random_bytes(80_000);
        $compressed = gzencode($plain, 1);
        $this->assertNotFalse($compressed);
        $this->assertGreaterThan(2048, strlen($compressed));
        $this->server = LocalHttpServerFixture::start([
            '/gzip' => [
                'status' => 200,
                'headers' => [
                    'Content-Type' => 'application/rss+xml',
                    'Content-Encoding' => 'gzip',
                    'Content-Length' => (string) strlen($compressed),
                ],
                'body' => $compressed,
            ],
        ]);
        $host = 'feeds.pin.test';
        $transport = new class extends CurlPinnedHttpTransport
        {
            public int $maxBytesObserved = 0;

            public function get(string $url, array $validatedIps, array $options): array
            {
                $options['max_body_bytes'] = 2048;
                $result = parent::get($url, $validatedIps, $options);
                $this->maxBytesObserved = max(
                    $this->maxBytesObserved,
                    strlen((string) $result['body']),
                    (int) $result['body_size'],
                );

                return $result;
            }
        };
        $result = (new LaravelSafeFeedFetcher(
            new LocalPinSafeUrlGuard([$host]),
            $transport,
        ))->fetch(
            'http://'.$host.':'.$this->server->port().'/gzip',
            [$host],
        );

        $this->assertFalse($result['success']);
        $this->assertSame('response_too_large', $result['error_code']);
        $this->assertSame('', $result['body']);
        $this->assertGreaterThan(2048, $result['response_size']);
        $this->assertLessThanOrEqual(2048, strlen((string) ($result['body'] ?? '')));
        $this->assertSame(0, strlen($result['body']));
        $this->assertFalse(str_contains($result['body'], substr($plain, 0, 32)));
    }

    public function test_chunked_oversized_response_is_bounded(): void
    {
        $this->requireCurl();
        $chunkBody = str_repeat('c', 64_000);
        $this->server = LocalHttpServerFixture::start([
            '/chunked' => [
                'status' => 200,
                'headers' => [
                    'Content-Type' => 'application/rss+xml',
                ],
                'body' => $chunkBody,
            ],
        ]);
        $host = 'feeds.pin.test';
        $transport = new class extends CurlPinnedHttpTransport
        {
            public string $lastTransportError = '';

            public function get(string $url, array $validatedIps, array $options): array
            {
                $options['max_body_bytes'] = 2048;
                $result = parent::get($url, $validatedIps, $options);
                $this->lastTransportError = (string) $result['transport_error'];

                return $result;
            }
        };
        $result = (new LaravelSafeFeedFetcher(
            new LocalPinSafeUrlGuard([$host]),
            $transport,
        ))->fetch(
            'http://'.$host.':'.$this->server->port().'/chunked',
            [$host],
        );

        $this->assertFalse($result['success'], $transport->lastTransportError);
        $this->assertSame('response_too_large', $result['error_code']);
        $this->assertSame('', $result['body']);
        $this->assertLessThanOrEqual(2049, $result['response_size']);
    }

    public function test_production_guard_still_rejects_literal_loopback_urls(): void
    {
        $result = (new LaravelSafeFeedFetcher(new SafeUrlGuard))->fetch(
            'http://127.0.0.1/private',
            ['127.0.0.1'],
        );

        $this->assertFalse($result['success']);
        $this->assertSame('url_blocked', $result['error_code']);
    }

    /** @param array<int, string> $hosts */
    private function fetcherForLocalPin(array $hosts): LaravelSafeFeedFetcher
    {
        return new LaravelSafeFeedFetcher(
            new LocalPinSafeUrlGuard($hosts),
            new CurlPinnedHttpTransport,
        );
    }

    private function requireCurl(): void
    {
        if (! extension_loaded('curl') || ! function_exists('curl_init')) {
            $this->fail('curl extension is required for acquisition transport integration tests');
        }
    }
}
