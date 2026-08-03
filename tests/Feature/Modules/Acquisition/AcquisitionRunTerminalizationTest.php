<?php

namespace Tests\Feature\Modules\Acquisition;

use App\Application\Services\EventBusService;
use App\Application\Services\FeatureFlagService;
use App\Application\Services\JobEngineService;
use App\Domain\Shared\Enums\FeatureFlagScope;
use App\Domain\Shared\Enums\PlatformJobStatus;
use App\Infrastructure\Persistence\Models\Project;
use App\Infrastructure\Persistence\Models\StoredEvent;
use App\Modules\Acquisition\Application\AcquisitionRunTerminalizer;
use App\Modules\Acquisition\Application\CapabilityGate;
use App\Modules\Acquisition\Domain\AcquisitionRunTerminalizationException;
use App\Modules\Acquisition\Domain\Events\AcquisitionRunFailed;
use App\Modules\Acquisition\Infrastructure\Http\FeedFetcherInterface;
use App\Modules\Acquisition\Infrastructure\Jobs\AcquireSourceJob;
use App\Modules\Acquisition\Infrastructure\Persistence\Models\AcquisitionRun;
use App\Modules\Acquisition\Infrastructure\Persistence\Models\Source;
use App\Modules\Acquisition\Infrastructure\Persistence\Repositories\EloquentAcquisitionRunRepository;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\Feature\Modules\Acquisition\Support\AcquisitionHttpTestBindings;
use Tests\Feature\Modules\Acquisition\Support\SequencedFeedFetcher;
use Tests\TestCase;

class AcquisitionRunTerminalizationTest extends TestCase
{
    public function test_completed_path_ends_completed(): void
    {
        $this->bindSuccessFetcher();
        ['organization' => $organization, 'project' => $project, 'source' => $source] = $this->seedTenant();
        $job = $this->dispatchAcquire($organization->id, $project->id, $source->id);

        $this->assertSame(PlatformJobStatus::Completed, $job->status);
        $this->assertDatabaseHas('acquisition_runs', [
            'organization_id' => $organization->id,
            'project_id' => $project->id,
            'status' => 'completed',
        ]);
        $this->assertSame(0, AcquisitionRun::query()->where('status', 'running')->count());
    }

    public function test_permanent_validation_failure_ends_failed(): void
    {
        ['user' => $owner, 'organization' => $organization] = $this->createUserWithOrg();
        $project = Project::create([
            'organization_id' => $organization->id,
            'name' => 'Terminal Permanent',
            'slug' => 'terminal-permanent',
            'created_by' => $owner->id,
        ]);
        $this->enableAcquisition($organization->id, $project->id);
        $job = app(JobEngineService::class)->create(
            'acquisition.acquire_source',
            $organization->id,
            $project->id,
            [
                'organization_id' => $organization->id,
                'project_id' => $project->id,
                'source_id' => (string) Str::uuid(),
                'trigger' => 'terminal-permanent',
            ],
        );

        AcquireSourceJob::dispatch($job->id);
        $job->refresh();

        $this->assertSame(PlatformJobStatus::Failed, $job->status);
        $this->assertSame('not_found', $job->error);
        $this->assertDatabaseHas('acquisition_runs', [
            'organization_id' => $organization->id,
            'project_id' => $project->id,
            'status' => 'failed',
            'error_code' => 'not_found',
        ]);
        $this->assertSame(0, AcquisitionRun::query()->where('status', 'running')->count());
    }

    public function test_retryable_exception_is_rethrown_after_failed_terminalization(): void
    {
        AcquisitionHttpTestBindings::bindFeedFetcher($this->app, new SequencedFeedFetcher([
            [
                'success' => false,
                'error_code' => 'http_error',
                'http_status' => 503,
                'body' => '',
            ],
        ]));
        ['organization' => $organization, 'project' => $project, 'source' => $source] = $this->seedTenant();
        $platformJob = app(JobEngineService::class)->create(
            'acquisition.acquire_source',
            $organization->id,
            $project->id,
            [
                'organization_id' => $organization->id,
                'project_id' => $project->id,
                'source_id' => $source->id,
                'trigger' => 'terminal-retryable',
            ],
        );

        try {
            (new AcquireSourceJob($platformJob->id))->handle(
                app(JobEngineService::class),
                app(EventBusService::class),
                app(\App\Modules\Acquisition\Application\ProductionAcquisitionOrchestrator::class),
                app(EloquentAcquisitionRunRepository::class),
                app(\App\Modules\Acquisition\Domain\Sources\SourceRepositoryInterface::class),
                app(CapabilityGate::class),
                app(AcquisitionRunTerminalizer::class),
            );
            $this->fail('Expected retryable RuntimeException');
        } catch (RuntimeException $exception) {
            $this->assertSame('http_error', $exception->getMessage());
        }

        $this->assertDatabaseHas('acquisition_runs', [
            'organization_id' => $organization->id,
            'project_id' => $project->id,
            'status' => 'failed',
            'error_code' => 'http_error',
        ]);
        $this->assertSame(0, AcquisitionRun::query()->where('status', 'running')->count());
    }

    public function test_first_terminal_update_failure_then_second_success(): void
    {
        $failures = 0;
        $repository = new class($failures) extends EloquentAcquisitionRunRepository
        {
            public function __construct(private int &$failures) {}

            public function updateRun(string $identifier, array $data): bool
            {
                if (($data['status'] ?? null) === 'completed' && $this->failures < 1) {
                    $this->failures++;

                    return false;
                }

                return parent::updateRun($identifier, $data);
            }
        };
        $this->app->instance(EloquentAcquisitionRunRepository::class, $repository);
        $this->app->forgetInstance(AcquisitionRunTerminalizer::class);
        $this->bindSuccessFetcher();
        ['organization' => $organization, 'project' => $project, 'source' => $source] = $this->seedTenant();
        $job = $this->dispatchAcquire($organization->id, $project->id, $source->id);

        $this->assertSame(PlatformJobStatus::Completed, $job->status);
        $this->assertSame(1, $failures);
        $this->assertDatabaseHas('acquisition_runs', [
            'organization_id' => $organization->id,
            'project_id' => $project->id,
            'status' => 'completed',
        ]);
        $this->assertSame(0, AcquisitionRun::query()->where('status', 'running')->count());
    }

    public function test_repeated_terminal_persistence_failure_throws_dedicated_exception(): void
    {
        $repository = new class extends EloquentAcquisitionRunRepository
        {
            public function updateRun(string $identifier, array $data): bool
            {
                if (in_array(($data['status'] ?? null), ['completed', 'failed'], true)) {
                    return false;
                }

                return parent::updateRun($identifier, $data);
            }
        };
        $this->app->instance(EloquentAcquisitionRunRepository::class, $repository);
        $this->app->forgetInstance(AcquisitionRunTerminalizer::class);
        $this->bindSuccessFetcher();
        ['organization' => $organization, 'project' => $project, 'source' => $source] = $this->seedTenant();
        $platformJob = app(JobEngineService::class)->create(
            'acquisition.acquire_source',
            $organization->id,
            $project->id,
            [
                'organization_id' => $organization->id,
                'project_id' => $project->id,
                'source_id' => $source->id,
                'trigger' => 'terminal-hard-fail',
            ],
        );

        try {
            (new AcquireSourceJob($platformJob->id))->handle(
                app(JobEngineService::class),
                app(EventBusService::class),
                app(\App\Modules\Acquisition\Application\ProductionAcquisitionOrchestrator::class),
                $repository,
                app(\App\Modules\Acquisition\Domain\Sources\SourceRepositoryInterface::class),
                app(CapabilityGate::class),
                new AcquisitionRunTerminalizer($repository),
            );
            $this->fail('Expected AcquisitionRunTerminalizationException');
        } catch (AcquisitionRunTerminalizationException $exception) {
            $this->assertStringContainsString('run_terminalization_failed', $exception->getMessage());
        }

        $this->assertSame(1, AcquisitionRun::query()->where('status', 'running')->count());
    }

    public function test_failed_job_hook_retries_terminalization(): void
    {
        ['organization' => $organization, 'project' => $project, 'source' => $source] = $this->seedTenant();
        $platformJob = app(JobEngineService::class)->create(
            'acquisition.acquire_source',
            $organization->id,
            $project->id,
            [
                'organization_id' => $organization->id,
                'project_id' => $project->id,
                'source_id' => $source->id,
                'trigger' => 'failed-hook',
            ],
        );
        $runId = (string) Str::uuid();
        AcquisitionRun::create([
            'organization_id' => $organization->id,
            'project_id' => $project->id,
            'run_id' => $runId,
            'status' => 'running',
            'sources_requested' => 1,
            'sources_succeeded' => 0,
            'sources_failed' => 0,
            'meta' => ['platform_job_id' => $platformJob->id],
        ]);

        $job = new AcquireSourceJob($platformJob->id);
        $job->failed(new RuntimeException('http_error'));

        $this->assertDatabaseHas('acquisition_runs', [
            'run_id' => $runId,
            'status' => 'failed',
            'error_code' => 'http_error',
        ]);
        $this->assertSame(0, AcquisitionRun::query()->where('status', 'running')->count());
        $this->assertSame(1, StoredEvent::query()
            ->where('event_type', 'acquisition.run_failed')
            ->count());
    }

    public function test_duplicate_acquisition_run_failed_event_is_prevented(): void
    {
        ['organization' => $organization, 'project' => $project, 'source' => $source] = $this->seedTenant();
        $platformJob = app(JobEngineService::class)->create(
            'acquisition.acquire_source',
            $organization->id,
            $project->id,
            [
                'organization_id' => $organization->id,
                'project_id' => $project->id,
                'source_id' => $source->id,
                'trigger' => 'dup-event',
            ],
        );
        $runId = (string) Str::uuid();
        AcquisitionRun::create([
            'organization_id' => $organization->id,
            'project_id' => $project->id,
            'run_id' => $runId,
            'status' => 'running',
            'sources_requested' => 1,
            'sources_succeeded' => 0,
            'sources_failed' => 0,
            'meta' => [
                'platform_job_id' => $platformJob->id,
                'failure_event_emitted' => true,
            ],
        ]);

        $job = new AcquireSourceJob($platformJob->id);
        $job->failed(new RuntimeException('http_error'));
        $job->failed(new RuntimeException('http_error'));

        $this->assertSame(0, StoredEvent::query()
            ->where('event_type', 'acquisition.run_failed')
            ->count());
        $this->assertDatabaseHas('acquisition_runs', [
            'run_id' => $runId,
            'status' => 'failed',
        ]);
    }

    public function test_terminal_completed_state_cannot_regress_to_running(): void
    {
        ['organization' => $organization, 'project' => $project] = $this->seedTenant();
        $runId = (string) Str::uuid();
        AcquisitionRun::create([
            'organization_id' => $organization->id,
            'project_id' => $project->id,
            'run_id' => $runId,
            'status' => 'completed',
            'sources_requested' => 1,
            'sources_succeeded' => 1,
            'sources_failed' => 0,
        ]);
        $updated = app(EloquentAcquisitionRunRepository::class)->updateRun($runId, [
            'organization_id' => $organization->id,
            'project_id' => $project->id,
            'status' => 'running',
        ]);

        $this->assertFalse($updated);
        $this->assertDatabaseHas('acquisition_runs', [
            'run_id' => $runId,
            'status' => 'completed',
        ]);
    }

    public function test_terminal_failed_state_cannot_regress_to_running(): void
    {
        ['organization' => $organization, 'project' => $project] = $this->seedTenant();
        $runId = (string) Str::uuid();
        AcquisitionRun::create([
            'organization_id' => $organization->id,
            'project_id' => $project->id,
            'run_id' => $runId,
            'status' => 'failed',
            'error_code' => 'http_error',
            'sources_requested' => 1,
            'sources_succeeded' => 0,
            'sources_failed' => 1,
        ]);
        $updated = app(EloquentAcquisitionRunRepository::class)->updateRun($runId, [
            'organization_id' => $organization->id,
            'project_id' => $project->id,
            'status' => 'running',
        ]);

        $this->assertFalse($updated);
        $this->assertDatabaseHas('acquisition_runs', [
            'run_id' => $runId,
            'status' => 'failed',
        ]);
    }

    /** @return array{organization: \App\Infrastructure\Persistence\Models\Organization, project: Project, source: Source} */
    private function seedTenant(): array
    {
        ['user' => $owner, 'organization' => $organization] = $this->createUserWithOrg();
        $project = Project::create([
            'organization_id' => $organization->id,
            'name' => 'Terminalization',
            'slug' => 'terminalization-'.uniqid(),
            'created_by' => $owner->id,
        ]);
        $this->enableAcquisition($organization->id, $project->id);
        $feedUrl = 'http://93.184.216.34/terminal.xml';
        $source = Source::create([
            'organization_id' => $organization->id,
            'project_id' => $project->id,
            'slug' => 'terminal-feed',
            'name' => 'Terminal Feed',
            'source_type' => 'rss',
            'base_url' => 'http://93.184.216.34',
            'feed_url' => $feedUrl,
            'feed_url_hash' => hash('sha256', $feedUrl),
            'allowed_domains' => ['93.184.216.34'],
            'enabled' => true,
            'manual_only' => false,
        ]);

        return compact('organization', 'project', 'source');
    }

    private function bindSuccessFetcher(): void
    {
        AcquisitionHttpTestBindings::bindFeedFetcher($this->app, new SequencedFeedFetcher([
            [
                'success' => true,
                'body' => '<?xml version="1.0"?><rss version="2.0"><channel>'.
                    '<title>T</title><item><title>One</title>'.
                    '<link>https://example.com/one</link><guid>one</guid></item></channel></rss>',
                'content_type' => 'application/rss+xml',
            ],
        ]));
    }

    private function dispatchAcquire(string $organizationId, string $projectId, string $sourceId): \App\Infrastructure\Persistence\Models\PlatformJob
    {
        $job = app(JobEngineService::class)->create(
            'acquisition.acquire_source',
            $organizationId,
            $projectId,
            [
                'organization_id' => $organizationId,
                'project_id' => $projectId,
                'source_id' => $sourceId,
                'trigger' => 'terminalization-test',
            ],
        );
        AcquireSourceJob::dispatch($job->id);
        $job->refresh();

        return $job;
    }

    private function enableAcquisition(string $organizationId, string $projectId): void
    {
        $flags = app(FeatureFlagService::class);

        foreach ([CapabilityGate::ACQUISITION, CapabilityGate::SOURCE_REGISTRY] as $key) {
            $flags->upsert(
                $key,
                true,
                FeatureFlagScope::Project,
                null,
                $organizationId,
                $projectId,
            );
        }
    }
}
