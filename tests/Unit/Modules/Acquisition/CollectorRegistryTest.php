<?php

namespace Tests\Unit\Modules\Acquisition;

use App\Modules\Acquisition\Domain\Collectors\CollectorInterface;
use App\Modules\Acquisition\Domain\Collectors\CollectorRegistry;
use PHPUnit\Framework\TestCase;

class CollectorRegistryTest extends TestCase
{
    public function test_registers_safe_feed_maps_source_types_and_resolves_collectors(): void
    {
        $collector = new StubSafeFeedCollector;
        $registry = new CollectorRegistry;
        $registry->register($collector);
        $registry->mapSourceType('RSS', 'safe_feed');
        $registry->mapSourceType('atom', 'safe_feed');
        $registry->mapSourceType('html', 'safe_feed');

        $this->assertTrue($registry->has('safe_feed'));
        $this->assertSame($collector, $registry->get('safe_feed'));
        $this->assertSame($collector, $registry->defaultCollector());
        $this->assertSame($collector, $registry->resolveForSourceType('rss'));
        $this->assertSame($collector, $registry->resolveForSourceType('ATOM'));
        $this->assertSame($collector, $registry->resolveForSourceType('unmapped'));
        $this->assertSame([
            'rss' => 'safe_feed',
            'atom' => 'safe_feed',
            'html' => 'safe_feed',
        ], $registry->sourceTypeMap());
    }
}

final class StubSafeFeedCollector implements CollectorInterface
{
    public function id(): string
    {
        return 'safe_feed';
    }

    public function collect(string $url, array $allowedDomains): array
    {
        return ['success' => true, 'url' => $url, 'allowed_domains' => $allowedDomains];
    }
}
