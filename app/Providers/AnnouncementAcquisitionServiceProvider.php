<?php

namespace App\Providers;

use App\Application\Services\FeatureFlagService;
use App\Application\Services\SchedulerService;
use App\Modules\Acquisition\Application\AcquisitionDiagnostics;
use App\Modules\Acquisition\Application\AcquisitionAwareSchedulerService;
use App\Modules\Acquisition\Application\AcquisitionEngine;
use App\Modules\Acquisition\Application\AcquisitionManager;
use App\Modules\Acquisition\Application\AcquisitionScheduleRegistrar;
use App\Modules\Acquisition\Application\CapabilityGate;
use App\Modules\Acquisition\Application\DownloadManager;
use App\Modules\Acquisition\Application\ProductionAcquisitionOrchestrator;
use App\Modules\Acquisition\Application\SourceAcquisitionService;
use App\Modules\Acquisition\Application\SourceConnectivityService;
use App\Modules\Acquisition\Application\SourceRegistryService;
use App\Modules\Acquisition\Domain\Collectors\CollectorRegistry;
use App\Modules\Acquisition\Domain\Contracts\CapabilityGateInterface as AcquisitionCapabilityGateInterface;
use App\Modules\Acquisition\Domain\Evidence\EvidenceRepositoryInterface;
use App\Modules\Acquisition\Domain\Feed\AsepAnnouncementsHtmlParser;
use App\Modules\Acquisition\Domain\Feed\FeedPreviewParser;
use App\Modules\Acquisition\Domain\Fingerprint\FingerprintService;
use App\Modules\Acquisition\Domain\Registry\AsepHtmlParserHandler;
use App\Modules\Acquisition\Domain\Registry\FeedPreviewParserHandler;
use App\Modules\Acquisition\Domain\Registry\ParserRegistry;
use App\Modules\Acquisition\Domain\Sources\SourceRepositoryInterface;
use App\Modules\Acquisition\Infrastructure\Collectors\SafeFeedCollector;
use App\Modules\Acquisition\Infrastructure\Evidence\InMemoryEvidenceRepository;
use App\Modules\Acquisition\Infrastructure\Http\FeedFetcherInterface;
use App\Modules\Acquisition\Infrastructure\Http\LaravelSafeFeedFetcher;
use App\Modules\Acquisition\Infrastructure\Http\SafeUrlGuard;
use App\Modules\Acquisition\Infrastructure\Persistence\Models\AcquisitionRun;
use App\Modules\Acquisition\Infrastructure\Persistence\Models\Source;
use App\Modules\Acquisition\Infrastructure\Persistence\Repositories\EloquentAcquisitionRunRepository;
use App\Modules\Acquisition\Infrastructure\Persistence\Repositories\EloquentSourceRepository;
use App\Modules\Announcement\Application\AnnouncementLifecycleService;
use App\Modules\Announcement\Application\EditorialIngestionService;
use App\Modules\Announcement\Application\EditorialWorkspaceQueryService;
use App\Modules\Announcement\Domain\AnnouncementIdentityService;
use App\Modules\Announcement\Domain\AnnouncementItemExtractor;
use App\Modules\Announcement\Domain\AnnouncementRepositoryInterface;
use App\Modules\Announcement\Domain\Contracts\CapabilityGateInterface as AnnouncementCapabilityGateInterface;
use App\Modules\Announcement\Domain\Contracts\CollectorRegistryInterface;
use App\Modules\Announcement\Domain\Contracts\IngestionDiagnosticsInterface;
use App\Modules\Announcement\Domain\Contracts\ParserRegistryInterface;
use App\Modules\Announcement\Infrastructure\Persistence\Models\Announcement;
use App\Modules\Announcement\Infrastructure\Persistence\Repositories\EloquentAnnouncementRepository;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AnnouncementAcquisitionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SafeUrlGuard::class);
        $this->app->singleton(LaravelSafeFeedFetcher::class);
        $this->app->alias(LaravelSafeFeedFetcher::class, FeedFetcherInterface::class);

        $this->app->singleton(InMemoryEvidenceRepository::class);
        $this->app->alias(InMemoryEvidenceRepository::class, EvidenceRepositoryInterface::class);

        $this->app->singleton(SafeFeedCollector::class);
        $this->app->singleton(CollectorRegistry::class, function ($app): CollectorRegistry {
            $registry = new CollectorRegistry;
            $registry->register($app->make(SafeFeedCollector::class));

            foreach (['rss', 'atom', 'html', 'json', 'xml', 'pdf'] as $sourceType) {
                $registry->mapSourceType($sourceType, 'safe_feed');
            }

            return $registry;
        });
        $this->app->alias(CollectorRegistry::class, CollectorRegistryInterface::class);

        $this->app->singleton(FeedPreviewParser::class);
        $this->app->singleton(AsepAnnouncementsHtmlParser::class);
        $this->app->singleton(FeedPreviewParserHandler::class);
        $this->app->singleton(AsepHtmlParserHandler::class);
        $this->app->singleton(ParserRegistry::class, function ($app): ParserRegistry {
            $registry = new ParserRegistry;
            $registry->register($app->make(FeedPreviewParserHandler::class));
            $registry->register($app->make(AsepHtmlParserHandler::class));

            return $registry;
        });
        $this->app->alias(ParserRegistry::class, ParserRegistryInterface::class);

        $this->app->singleton(FingerprintService::class);
        $this->app->singleton(DownloadManager::class);

        $this->app->singleton(EloquentSourceRepository::class);
        $this->app->alias(EloquentSourceRepository::class, SourceRepositoryInterface::class);
        $this->app->singleton(EloquentAnnouncementRepository::class);
        $this->app->alias(EloquentAnnouncementRepository::class, AnnouncementRepositoryInterface::class);
        $this->app->singleton(EloquentAcquisitionRunRepository::class);

        // Fail-closed: missing/unset FeatureFlag rows keep acquisition disabled.
        $this->app->singleton(
            CapabilityGate::class,
            static fn ($app): CapabilityGate => new CapabilityGate($app->make(FeatureFlagService::class)),
        );
        $this->app->alias(CapabilityGate::class, AcquisitionCapabilityGateInterface::class);
        $this->app->alias(CapabilityGate::class, AnnouncementCapabilityGateInterface::class);
        $this->app->singleton(SchedulerService::class, AcquisitionAwareSchedulerService::class);

        $this->app->singleton(AcquisitionDiagnostics::class, function ($app): AcquisitionDiagnostics {
            return new AcquisitionDiagnostics(
                $app->make(CollectorRegistry::class),
                $app->make(ParserRegistry::class),
                $app->make(EvidenceRepositoryInterface::class),
                $app->make(AcquisitionCapabilityGateInterface::class),
                $app->make(FingerprintService::class),
            );
        });
        $this->app->alias(AcquisitionDiagnostics::class, IngestionDiagnosticsInterface::class);

        foreach ([
            AcquisitionManager::class,
            AcquisitionEngine::class,
            SourceAcquisitionService::class,
            ProductionAcquisitionOrchestrator::class,
            AcquisitionScheduleRegistrar::class,
            SourceRegistryService::class,
            SourceConnectivityService::class,
            AnnouncementIdentityService::class,
            AnnouncementItemExtractor::class,
            AnnouncementLifecycleService::class,
            EditorialWorkspaceQueryService::class,
            EditorialIngestionService::class,
        ] as $service) {
            $this->app->singleton($service);
        }
    }

    public function boot(): void
    {
        Route::bind('source', function (string $value, $route): Source {
            [$organizationId, $projectId] = $this->tenantIds($route);

            return Source::query()
                ->whereKey($value)
                ->where('organization_id', $organizationId)
                ->where('project_id', $projectId)
                ->firstOrFail();
        });

        Route::bind('announcement', function (string $value, $route): Announcement {
            [$organizationId, $projectId] = $this->tenantIds($route);

            return Announcement::query()
                ->whereKey($value)
                ->where('organization_id', $organizationId)
                ->where('project_id', $projectId)
                ->firstOrFail();
        });

        $acquisitionRunBinding = function (string $value, $route): AcquisitionRun {
            [$organizationId, $projectId] = $this->tenantIds($route);

            return AcquisitionRun::query()
                ->where(function ($query) use ($value): void {
                    $query->whereKey($value)->orWhere('run_id', $value);
                })
                ->where('organization_id', $organizationId)
                ->where('project_id', $projectId)
                ->firstOrFail();
        };
        Route::bind('acquisition_run', $acquisitionRunBinding);
        Route::bind('acquisitionRun', $acquisitionRunBinding);
        Route::bind('run', $acquisitionRunBinding);
    }

    /** @return array{0: string, 1: string} */
    private function tenantIds(mixed $route): array
    {
        $organization = $route->parameter('organization');
        $project = $route->parameter('project');

        $organizationId = is_object($organization) ? (string) $organization->id : (string) $organization;
        $projectId = is_object($project) ? (string) $project->id : (string) $project;

        // Operator Blade UI selects tenant via session rather than route params.
        if ($organizationId === '' || $projectId === '') {
            $organizationId = (string) (\App\Support\OperatorContext::organizationId()
                ?? request()->attributes->get('organization_id')
                ?? '');
            $projectId = (string) (\App\Support\OperatorContext::projectId()
                ?? request()->attributes->get('project_id')
                ?? '');
        }

        return [$organizationId, $projectId];
    }
}
