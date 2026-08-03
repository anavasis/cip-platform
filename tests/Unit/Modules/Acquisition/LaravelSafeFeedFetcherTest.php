<?php

namespace Tests\Unit\Modules\Acquisition;

use App\Modules\Acquisition\Infrastructure\Http\LaravelSafeFeedFetcher;
use App\Modules\Acquisition\Infrastructure\Http\SafeUrlGuard;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LaravelSafeFeedFetcherTest extends TestCase
{
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
}
