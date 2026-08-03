<?php

namespace Tests\Unit\Modules\Acquisition;

use App\Modules\Acquisition\Infrastructure\Http\CurlPinnedHttpTransport;
use App\Modules\Acquisition\Infrastructure\Http\LaravelSafeFeedFetcher;
use App\Modules\Acquisition\Infrastructure\Http\SafeUrlGuard;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\Handler\StreamHandler;
use GuzzleHttp\HandlerStack;
use Tests\TestCase;

class LaravelSafeFeedFetcherTest extends TestCase
{
    public function test_transport_constructs_explicit_curl_handler_without_stream_handler(): void
    {
        $transport = new CurlPinnedHttpTransport;
        $client = $transport->createCurlClient();

        $this->assertTrue($transport->usesCurlHandler($client));

        $stack = $client->getConfig('handler');
        $this->assertInstanceOf(HandlerStack::class, $stack);

        $reflection = new \ReflectionClass($stack);
        $property = $reflection->getProperty('handler');
        $property->setAccessible(true);
        $handler = $property->getValue($stack);

        $this->assertInstanceOf(CurlHandler::class, $handler);
        $this->assertNotInstanceOf(StreamHandler::class, $handler);
    }

    public function test_missing_curl_fails_closed_with_normalized_transport_error(): void
    {
        $transport = new class extends CurlPinnedHttpTransport
        {
            public function assertCurlTransportAvailable(): void
            {
                throw new \RuntimeException('curl_extension_unavailable');
            }
        };
        $guard = new SafeUrlGuard(static fn (string $host): array => ['93.184.216.34']);
        $result = (new LaravelSafeFeedFetcher($guard, $transport))->fetch(
            'https://feeds.example.test/rss',
            ['feeds.example.test'],
        );

        $this->assertFalse($result['success']);
        $this->assertSame('transport_error', $result['error_code']);
        $this->assertSame('', $result['body']);
    }

    public function test_validated_address_is_pinned_into_curl_resolve_string(): void
    {
        $transport = new CurlPinnedHttpTransport;
        $connection = $transport->pinnedConnection('https://feeds.example.test/rss', ['93.184.216.34']);

        $this->assertNotNull($connection);
        $this->assertSame('feeds.example.test:443:93.184.216.34', $connection['resolve']);
        $this->assertSame('feeds.example.test', $connection['host_header']);
    }

    public function test_redirect_target_is_revalidated_and_private_ip_is_blocked_without_follow(): void
    {
        $transport = new class extends CurlPinnedHttpTransport
        {
            public int $calls = 0;

            public function get(string $url, array $validatedIps, array $options): array
            {
                $this->calls++;

                return [
                    'transport_error' => '',
                    'status_code' => 302,
                    'content_type' => '',
                    'location' => 'http://127.0.0.1/internal',
                    'body' => '',
                    'truncated_prefix' => '',
                    'body_size' => 0,
                    'body_too_large' => false,
                ];
            }
        };
        $result = (new LaravelSafeFeedFetcher(new SafeUrlGuard, $transport))->fetch(
            'http://93.184.216.34/feed',
            ['93.184.216.34', '127.0.0.1'],
        );

        $this->assertFalse($result['success']);
        $this->assertSame('redirect_blocked', $result['error_code']);
        $this->assertSame(302, $result['http_status']);
        $this->assertSame('', $result['body']);
        $this->assertSame(1, $transport->calls);
    }

    public function test_each_redirect_hop_is_revalidated_and_repinned(): void
    {
        $pins = [];
        $transport = new class($pins) extends CurlPinnedHttpTransport
        {
            /** @param array<int, string> $pins */
            public function __construct(private array &$pins) {}

            public function get(string $url, array $validatedIps, array $options): array
            {
                $connection = $this->pinnedConnection($url, $validatedIps);
                $this->pins[] = $connection['resolve'] ?? '';

                return str_contains($url, 'one.example.test')
                    ? [
                        'transport_error' => '',
                        'status_code' => 302,
                        'content_type' => '',
                        'location' => 'https://two.example.test/feed',
                        'body' => '',
                        'truncated_prefix' => '',
                        'body_size' => 0,
                        'body_too_large' => false,
                    ]
                    : [
                        'transport_error' => '',
                        'status_code' => 200,
                        'content_type' => 'application/rss+xml',
                        'location' => '',
                        'body' => '<rss/>',
                        'truncated_prefix' => '',
                        'body_size' => 6,
                        'body_too_large' => false,
                    ];
            }
        };
        $guard = new SafeUrlGuard(
            static fn (string $host): array => match ($host) {
                'one.example.test' => ['93.184.216.34'],
                'two.example.test' => ['1.1.1.1'],
                default => [],
            },
        );

        $result = (new LaravelSafeFeedFetcher($guard, $transport))->fetch(
            'https://one.example.test/feed',
            ['one.example.test', 'two.example.test'],
        );

        $this->assertTrue($result['success']);
        $this->assertSame([
            'one.example.test:443:93.184.216.34',
            'two.example.test:443:1.1.1.1',
        ], $pins);
    }

    public function test_fetcher_source_forbids_stream_handler_fallback(): void
    {
        $fetcher = (string) file_get_contents(base_path(
            'app/Modules/Acquisition/Infrastructure/Http/LaravelSafeFeedFetcher.php',
        ));
        $transport = (string) file_get_contents(base_path(
            'app/Modules/Acquisition/Infrastructure/Http/CurlPinnedHttpTransport.php',
        ));

        $this->assertStringContainsString('CurlPinnedHttpTransport', $fetcher);
        $this->assertStringNotContainsString('Illuminate\\Support\\Facades\\Http', $fetcher);
        $this->assertStringContainsString('new CurlHandler', $transport);
        $this->assertStringContainsString('stream_handler_forbidden', $transport);
        $this->assertStringContainsString('CURLOPT_RESOLVE', $transport);
        $this->assertStringContainsString("'allow_redirects' => false", $transport);
        $this->assertStringContainsString('CURLOPT_FOLLOWLOCATION => false', $transport);
    }
}
