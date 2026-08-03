<?php

namespace App\Providers;

use App\Application\Services\AuditService;
use App\Application\Services\AuthService;
use App\Application\Services\ConfigurationService;
use App\Application\Services\ConnectorRegistryService;
use App\Application\Services\DiagnosticsService;
use App\Application\Services\EventBusService;
use App\Application\Services\FeatureFlagService;
use App\Application\Services\JobEngineService;
use App\Application\Services\MonitoringService;
use App\Application\Services\OrganizationService;
use App\Application\Services\PermissionService;
use App\Application\Services\ProjectService;
use App\Application\Services\SchedulerService;
use App\Application\Services\SecretEncryptionService;
use App\Application\Services\SecretService;
use App\Infrastructure\Persistence\Models\Organization;
use App\Infrastructure\Persistence\Models\Project;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class PlatformServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PermissionService::class);
        $this->app->singleton(SecretEncryptionService::class);
        $this->app->singleton(AuditService::class);
        $this->app->singleton(EventBusService::class);
        $this->app->singleton(JobEngineService::class);
        $this->app->singleton(MonitoringService::class);
        $this->app->singleton(DiagnosticsService::class);
        $this->app->singleton(ConnectorRegistryService::class);
        $this->app->singleton(ConfigurationService::class);
        $this->app->singleton(SecretService::class);
        $this->app->singleton(FeatureFlagService::class);
        $this->app->singleton(OrganizationService::class);
        $this->app->singleton(ProjectService::class);
        $this->app->singleton(AuthService::class);
        $this->app->singleton(SchedulerService::class);
    }

    public function boot(): void
    {
        Route::bind('organization', function (string $value) {
            return Organization::findOrFail($value);
        });

        Route::bind('project', function (string $value, $route) {
            $organization = $route->parameter('organization');
            $organizationId = $organization instanceof Organization ? $organization->id : $organization;

            return Project::query()
                ->where('id', $value)
                ->where('organization_id', $organizationId)
                ->firstOrFail();
        });

        Route::bind('secret', function (string $value, $route) {
            $organization = $route->parameter('organization');
            $organizationId = $organization instanceof Organization ? $organization->id : $organization;

            return \App\Infrastructure\Persistence\Models\Secret::query()
                ->where('id', $value)
                ->where('organization_id', $organizationId)
                ->firstOrFail();
        });

        Route::bind('schedule', function (string $value, $route) {
            $organization = $route->parameter('organization');
            $organizationId = $organization instanceof Organization ? $organization->id : $organization;

            return \App\Infrastructure\Persistence\Models\ScheduleDefinition::query()
                ->where('id', $value)
                ->where('organization_id', $organizationId)
                ->firstOrFail();
        });
    }
}
