<?php

namespace Tests\Feature\Modules\Acquisition\Support;

use App\Modules\Acquisition\Application\AcquisitionDiagnostics;
use App\Modules\Acquisition\Application\AcquisitionEngine;
use App\Modules\Acquisition\Application\AcquisitionManager;
use App\Modules\Acquisition\Application\DownloadManager;
use App\Modules\Acquisition\Application\ProductionAcquisitionOrchestrator;
use App\Modules\Acquisition\Application\SourceAcquisitionService;
use App\Modules\Acquisition\Application\SourceConnectivityService;
use App\Modules\Acquisition\Domain\Collectors\CollectorRegistry;
use App\Modules\Acquisition\Infrastructure\Collectors\SafeFeedCollector;
use App\Modules\Acquisition\Infrastructure\Http\CurlPinnedHttpTransport;
use App\Modules\Acquisition\Infrastructure\Http\FeedFetcherInterface;
use App\Modules\Acquisition\Infrastructure\Http\LaravelSafeFeedFetcher;
use Illuminate\Contracts\Foundation\Application;

/**
 * Rebinds acquisition HTTP dependencies after replacing the feed fetcher/transport.
 * Provider bindings remain unchanged; tests only swap container instances.
 */
final class AcquisitionHttpTestBindings
{
    public static function bindFeedFetcher(Application $app, FeedFetcherInterface $fetcher): void
    {
        $app->instance(FeedFetcherInterface::class, $fetcher);
        self::forgetHttpGraph($app);

        $app->singleton(SafeFeedCollector::class, static fn (): SafeFeedCollector => new SafeFeedCollector($fetcher));
        $app->singleton(CollectorRegistry::class, static function () use ($fetcher): CollectorRegistry {
            $registry = new CollectorRegistry;
            $registry->register(new SafeFeedCollector($fetcher));

            foreach (['rss', 'atom', 'html', 'json', 'xml', 'pdf'] as $sourceType) {
                $registry->mapSourceType($sourceType, 'safe_feed');
            }

            return $registry;
        });
    }

    public static function bindTransport(Application $app, CurlPinnedHttpTransport $transport): void
    {
        $app->instance(CurlPinnedHttpTransport::class, $transport);
        self::forgetHttpGraph($app);
    }

    private static function forgetHttpGraph(Application $app): void
    {
        foreach ([
            LaravelSafeFeedFetcher::class,
            SafeFeedCollector::class,
            CollectorRegistry::class,
            DownloadManager::class,
            AcquisitionManager::class,
            AcquisitionEngine::class,
            SourceAcquisitionService::class,
            ProductionAcquisitionOrchestrator::class,
            SourceConnectivityService::class,
            AcquisitionDiagnostics::class,
        ] as $abstract) {
            $app->forgetInstance($abstract);
        }
    }
}
