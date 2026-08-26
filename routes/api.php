<?php

use App\Http\Controllers\Api\V1\AuditController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ConfigurationController;
use App\Http\Controllers\Api\V1\ConnectorController;
use App\Http\Controllers\Api\V1\DiagnosticsController;
use App\Http\Controllers\Api\V1\EventController;
use App\Http\Controllers\Api\V1\FeatureFlagController;
use App\Http\Controllers\Api\V1\JobController;
use App\Http\Controllers\Api\V1\MonitoringController;
use App\Http\Controllers\Api\V1\OrganizationController;
use App\Http\Controllers\Api\V1\OrganizationMembershipController;
use App\Http\Controllers\Api\V1\ProjectController;
use App\Http\Controllers\Api\V1\ProjectMembershipController;
use App\Http\Controllers\Api\V1\ScheduleController;
use App\Http\Controllers\Api\V1\SecretController;
use App\Http\Middleware\EnforcePermission;
use App\Http\Middleware\EnsureOrganizationAccess;
use App\Http\Middleware\EnsureProjectAccess;
use App\Http\Middleware\ForceJsonResponse;
use App\Modules\Acquisition\Http\Controllers\AcquisitionDiagnosticsController;
use App\Modules\Acquisition\Http\Controllers\AcquisitionRunController;
use App\Modules\Acquisition\Http\Controllers\SourceConnectivityController;
use App\Modules\Acquisition\Http\Controllers\SourceController;
use App\Modules\Announcement\Http\Controllers\AnnouncementController;
use App\Modules\Announcement\Http\Controllers\IngestionController;
use App\Modules\Editorial\Http\Controllers\EditorialArtifactController;
use App\Modules\Editorial\Http\Controllers\EditorialDiagnosticsController;
use App\Modules\Editorial\Http\Controllers\EditorialGenerationController;
use App\Modules\Intelligence\Http\Controllers\HubController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->middleware([ForceJsonResponse::class])
    ->group(function (): void {
        Route::prefix('auth')->group(function (): void {
            Route::post('register', [AuthController::class, 'register']);
            Route::post('login', [AuthController::class, 'login']);

            Route::middleware('auth:sanctum')->group(function (): void {
                Route::post('logout', [AuthController::class, 'logout']);
                Route::get('me', [AuthController::class, 'me']);
            });
        });

        Route::get('diagnostics/health', [DiagnosticsController::class, 'health']);

        Route::middleware('auth:sanctum')->group(function (): void {
            Route::get('organizations', [OrganizationController::class, 'index']);
            Route::post('organizations', [OrganizationController::class, 'store']);

            Route::get('flags', [FeatureFlagController::class, 'index']);
            Route::post('flags', [FeatureFlagController::class, 'upsert'])
                ->middleware(EnforcePermission::class.':flags.manage');
            Route::get('events', [EventController::class, 'index']);
            Route::get('audit', [AuditController::class, 'index'])
                ->middleware(EnforcePermission::class.':audit.view');
            Route::get('jobs', [JobController::class, 'index'])
                ->middleware(EnforcePermission::class.':jobs.view');
            Route::post('jobs/ping', [JobController::class, 'dispatchPing'])
                ->middleware(EnforcePermission::class.':jobs.view');
            Route::post('schedules/run-due', [ScheduleController::class, 'runDue'])
                ->middleware(EnforcePermission::class.':jobs.view');
            Route::get('connectors/types', [ConnectorController::class, 'types'])
                ->middleware(EnforcePermission::class.':connectors.view');
            Route::post('connectors/types', [ConnectorController::class, 'registerType'])
                ->middleware(EnforcePermission::class.':connectors.manage');
            Route::get('monitoring/metrics', [MonitoringController::class, 'index'])
                ->middleware(EnforcePermission::class.':diagnostics.view');
            Route::post('monitoring/metrics', [MonitoringController::class, 'store'])
                ->middleware(EnforcePermission::class.':diagnostics.view');

            Route::prefix('organizations/{organization}')
                ->middleware(EnsureOrganizationAccess::class)
                ->group(function (): void {
                    Route::get('/', [OrganizationController::class, 'show'])
                        ->middleware(EnforcePermission::class.':organizations.view');
                    Route::put('/', [OrganizationController::class, 'update'])
                        ->middleware(EnforcePermission::class.':organizations.manage');
                    Route::patch('/', [OrganizationController::class, 'update'])
                        ->middleware(EnforcePermission::class.':organizations.manage');
                    Route::delete('/', [OrganizationController::class, 'destroy'])
                        ->middleware(EnforcePermission::class.':organizations.manage');

                    Route::get('memberships', [OrganizationMembershipController::class, 'index'])
                        ->middleware(EnforcePermission::class.':organizations.view');
                    Route::post('memberships', [OrganizationMembershipController::class, 'store'])
                        ->middleware(EnforcePermission::class.':organizations.manage');

                    Route::get('projects', [ProjectController::class, 'index'])
                        ->middleware(EnforcePermission::class.':projects.view');
                    Route::post('projects', [ProjectController::class, 'store'])
                        ->middleware(EnforcePermission::class.':projects.manage');
                    Route::get('projects/{project}', [ProjectController::class, 'show'])
                        ->middleware(EnforcePermission::class.':projects.view');
                    Route::put('projects/{project}', [ProjectController::class, 'update'])
                        ->middleware(EnforcePermission::class.':projects.manage');
                    Route::patch('projects/{project}', [ProjectController::class, 'update'])
                        ->middleware(EnforcePermission::class.':projects.manage');
                    Route::delete('projects/{project}', [ProjectController::class, 'destroy'])
                        ->middleware(EnforcePermission::class.':projects.manage');

                    Route::get('config', [ConfigurationController::class, 'index'])
                        ->middleware(EnforcePermission::class.':config.manage');
                    Route::get('config/{key}', [ConfigurationController::class, 'show'])
                        ->middleware(EnforcePermission::class.':config.manage');
                    Route::post('config', [ConfigurationController::class, 'store'])
                        ->middleware(EnforcePermission::class.':config.manage');

                    Route::get('secrets', [SecretController::class, 'index'])
                        ->middleware(EnforcePermission::class.':secrets.manage');
                    Route::post('secrets', [SecretController::class, 'store'])
                        ->middleware(EnforcePermission::class.':secrets.manage');
                    Route::put('secrets/{secret}', [SecretController::class, 'update'])
                        ->middleware(EnforcePermission::class.':secrets.manage');
                    Route::delete('secrets/{secret}', [SecretController::class, 'destroy'])
                        ->middleware(EnforcePermission::class.':secrets.manage');
                    Route::get('secrets/{secret}/reveal', [SecretController::class, 'reveal'])
                        ->middleware(EnforcePermission::class.':secrets.reveal');

                    Route::get('flags', [FeatureFlagController::class, 'index'])
                        ->middleware(EnforcePermission::class.':flags.manage');
                    Route::post('flags', [FeatureFlagController::class, 'upsert'])
                        ->middleware(EnforcePermission::class.':flags.manage');

                    Route::get('audit', [AuditController::class, 'index'])
                        ->middleware(EnforcePermission::class.':audit.view');

                    Route::get('jobs', [JobController::class, 'index'])
                        ->middleware(EnforcePermission::class.':jobs.view');
                    Route::post('jobs/ping', [JobController::class, 'dispatchPing'])
                        ->middleware(EnforcePermission::class.':jobs.view');

                    Route::get('schedules', [ScheduleController::class, 'index'])
                        ->middleware(EnforcePermission::class.':jobs.view');
                    Route::post('schedules', [ScheduleController::class, 'store'])
                        ->middleware(EnforcePermission::class.':jobs.view');
                    Route::get('schedules/{schedule}', [ScheduleController::class, 'show'])
                        ->middleware(EnforcePermission::class.':jobs.view');
                    Route::put('schedules/{schedule}', [ScheduleController::class, 'update'])
                        ->middleware(EnforcePermission::class.':jobs.view');
                    Route::delete('schedules/{schedule}', [ScheduleController::class, 'destroy'])
                        ->middleware(EnforcePermission::class.':jobs.view');

                    Route::prefix('projects/{project}')
                        ->middleware([EnsureProjectAccess::class])
                        ->group(function (): void {
                            Route::get('memberships', [ProjectMembershipController::class, 'index'])
                                ->middleware(EnforcePermission::class.':projects.view');
                            Route::post('memberships', [ProjectMembershipController::class, 'store'])
                                ->middleware(EnforcePermission::class.':projects.manage');

                            Route::get('config', [ConfigurationController::class, 'index'])
                                ->middleware(EnforcePermission::class.':config.manage');
                            Route::post('config', [ConfigurationController::class, 'store'])
                                ->middleware(EnforcePermission::class.':config.manage');

                            Route::get('secrets', [SecretController::class, 'index'])
                                ->middleware(EnforcePermission::class.':secrets.manage');
                            Route::post('secrets', [SecretController::class, 'store'])
                                ->middleware(EnforcePermission::class.':secrets.manage');

                            Route::get('flags', [FeatureFlagController::class, 'index'])
                                ->middleware(EnforcePermission::class.':flags.manage');
                            Route::post('flags', [FeatureFlagController::class, 'upsert'])
                                ->middleware(EnforcePermission::class.':flags.manage');

                            Route::get('connectors', [ConnectorController::class, 'index'])
                                ->middleware(EnforcePermission::class.':connectors.view');
                            Route::post('connectors', [ConnectorController::class, 'attach'])
                                ->middleware(EnforcePermission::class.':connectors.manage');

                            Route::post('jobs/ping', [JobController::class, 'dispatchPing'])
                                ->middleware(EnforcePermission::class.':jobs.view');

                            Route::get('sources', [SourceController::class, 'index'])
                                ->middleware(EnforcePermission::class.':sources.view');
                            Route::post('sources', [SourceController::class, 'store'])
                                ->middleware(EnforcePermission::class.':sources.manage');
                            Route::get('sources/{source}', [SourceController::class, 'show'])
                                ->middleware(EnforcePermission::class.':sources.view');
                            Route::put('sources/{source}', [SourceController::class, 'update'])
                                ->middleware(EnforcePermission::class.':sources.manage');
                            Route::patch('sources/{source}', [SourceController::class, 'update'])
                                ->middleware(EnforcePermission::class.':sources.manage');
                            Route::delete('sources/{source}', [SourceController::class, 'destroy'])
                                ->middleware(EnforcePermission::class.':sources.manage');
                            Route::post('sources/{source}/enable', [SourceController::class, 'enable'])
                                ->middleware(EnforcePermission::class.':sources.manage');
                            Route::post('sources/{source}/disable', [SourceController::class, 'disable'])
                                ->middleware(EnforcePermission::class.':sources.manage');
                            Route::post('sources/{source}/run', [AcquisitionRunController::class, 'store'])
                                ->middleware(EnforcePermission::class.':sources.run');
                            Route::post('sources/{source}/check', [SourceConnectivityController::class, 'store'])
                                ->middleware(EnforcePermission::class.':sources.run');
                            Route::post('sources/{source}/ingest', [IngestionController::class, 'store'])
                                ->middleware(EnforcePermission::class.':acquisition.run');

                            Route::get('announcements', [AnnouncementController::class, 'index'])
                                ->middleware(EnforcePermission::class.':announcements.view');
                            Route::get('announcements/summary', [AnnouncementController::class, 'summary'])
                                ->middleware(EnforcePermission::class.':announcements.view');
                            Route::get('announcements/{announcement}', [AnnouncementController::class, 'show'])
                                ->middleware(EnforcePermission::class.':announcements.view');

                            Route::get('acquisition/runs', [AcquisitionRunController::class, 'index'])
                                ->middleware(EnforcePermission::class.':acquisition.view');
                            Route::post('acquisition/runs', [AcquisitionRunController::class, 'store'])
                                ->middleware(EnforcePermission::class.':acquisition.run');
                            Route::get('acquisition/runs/{acquisitionRun}', [AcquisitionRunController::class, 'show'])
                                ->middleware(EnforcePermission::class.':acquisition.view');
                            Route::get('acquisition/diagnostics', [AcquisitionDiagnosticsController::class, 'show'])
                                ->middleware(EnforcePermission::class.':acquisition.diagnostics');

                            Route::post('editorial/announcements/{announcement}/generate', [EditorialGenerationController::class, 'generate'])
                                ->middleware(EnforcePermission::class.':editorial.generate');
                            Route::post('editorial/announcements/{announcement}/regenerate', [EditorialGenerationController::class, 'regenerate'])
                                ->middleware(EnforcePermission::class.':editorial.regenerate');
                            Route::get('editorial/announcements/{announcement}/generation', [EditorialGenerationController::class, 'showGeneration'])
                                ->middleware(EnforcePermission::class.':editorial.view');
                            Route::get('editorial/generations', [EditorialGenerationController::class, 'indexGenerations'])
                                ->middleware(EnforcePermission::class.':editorial.view');
                            Route::get('editorial/blueprints/{blueprint}', [EditorialArtifactController::class, 'showBlueprint'])
                                ->middleware(EnforcePermission::class.':editorial.view');
                            Route::get('editorial/prompt-contexts/{promptContext}', [EditorialArtifactController::class, 'showPromptContext'])
                                ->middleware(EnforcePermission::class.':editorial.view');
                            Route::get('editorial/prompt-packages/{promptPackage}', [EditorialArtifactController::class, 'showPromptPackage'])
                                ->middleware(EnforcePermission::class.':editorial.view');
                            Route::get('editorial/generation-requests/{generationRequest}', [EditorialArtifactController::class, 'showGenerationRequest'])
                                ->middleware(EnforcePermission::class.':editorial.view');
                            Route::get('editorial/generation-results/{generationResult}', [EditorialArtifactController::class, 'showGenerationResult'])
                                ->middleware(EnforcePermission::class.':editorial.view');
                            Route::get('editorial/announcements/{announcement}/preview', [EditorialArtifactController::class, 'showPreview'])
                                ->middleware(EnforcePermission::class.':editorial.view');
                            Route::get('editorial/diagnostics', [EditorialDiagnosticsController::class, 'show'])
                                ->middleware(EnforcePermission::class.':editorial.diagnostics');

                            Route::get('hub', [HubController::class, 'show'])
                                ->middleware(EnforcePermission::class.':hub.view');
                        });
                });
        });
    });
