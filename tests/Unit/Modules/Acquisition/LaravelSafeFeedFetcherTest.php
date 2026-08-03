<?php

namespace Tests\Unit\Modules\Acquisition;

use App\Modules\Acquisition\Infrastructure\Http\LaravelSafeFeedFetcher;
use App\Modules\Acquisition\Infrastructure\Http\SafeUrlGuard;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LaravelSafeFeedFetcherTest extends TestCase
{
    public function test_validated_address_is_pinned_into_curl_transport_options(): void
    {
        $resolutionCalls = 0;
        $capturedOptions = [];
        $capturedHost = '';
        $guard = new SafeUrlGuard(
            static function (string $host) use (&$resolutionCalls): array {
                $resolutionCalls++;

                return $resolutionCalls === 1
                    ? ['93.184.216.34']
                    : ['127.0.0.1'];
            },
        );
        Http::fake(function ($request, array $options) use (&$capturedOptions, &$capturedHost) {
            $capturedOptions = $options;
            $capturedHost = (string) $request->header('Host')[0];

            return Http::response('<rss/>', 200, ['Content-Type' => 'application/rss+xml']);
        });

        $result = (new LaravelSafeFeedFetcher($guard))->fetch(
            'https://feeds.example.test/rss',
            ['feeds.example.test'],
        );
        $resolveOption = defined('CURLOPT_RESOLVE') ? constant('CURLOPT_RESOLVE') : 10203;

        $this->assertTrue($result['success']);
        $this->assertSame(1, $resolutionCalls);
        $this->assertSame(
            ['feeds.example.test:443:93.184.216.34'],
            $capturedOptions['curl'][$resolveOption],
        );
        $this->assertFalse($capturedOptions['allow_redirects']);
        $this->assertTrue($capturedOptions['verify']);
        $this->assertSame('feeds.example.test', $capturedHost);
    }

    public function test_redirect_target_is_revalidated_and_private_ip_is_blocked(): void
    {
        Http::fake([
            'http://93.184.216.34/feed' => Http::response('', 302, [
                'Location' => 'http://127.0.0.1/internal',
            ]),
        ]);

        $result = (new LaravelSafeFeedFetcher(new SafeUrlGuard))->fetch(
            'http://93.184.216.34/feed',
            ['93.184.216.34', '127.0.0.1'],
        );

        $this->assertFalse($result['success']);
        $this->assertSame('redirect_blocked', $result['error_code']);
        $this->assertSame(302, $result['http_status']);
        $this->assertSame('', $result['body']);
        Http::assertSentCount(1);
        Http::assertNotSent(static fn ($request): bool => str_contains($request->url(), '127.0.0.1'));
    }

    public function test_each_redirect_hop_is_revalidated_and_repinned(): void
    {
        $pins = [];
        $guard = new SafeUrlGuard(
            static fn (string $host): array => match ($host) {
                'one.example.test' => ['93.184.216.34'],
                'two.example.test' => ['1.1.1.1'],
                default => [],
            },
        );
        Http::fake(function ($request, array $options) use (&$pins) {
            $resolveOption = defined('CURLOPT_RESOLVE') ? constant('CURLOPT_RESOLVE') : 10203;
            $pins[] = $options['curl'][$resolveOption][0];

            return str_contains($request->url(), 'one.example.test')
                ? Http::response('', 302, ['Location' => 'https://two.example.test/feed'])
                : Http::response('<rss/>', 200, ['Content-Type' => 'application/rss+xml']);
        });

        $result = (new LaravelSafeFeedFetcher($guard))->fetch(
            'https://one.example.test/feed',
            ['one.example.test', 'two.example.test'],
        );

        $this->assertTrue($result['success']);
        $this->assertSame([
            'one.example.test:443:93.184.216.34',
            'two.example.test:443:1.1.1.1',
        ], $pins);
    }
}
