<?php

namespace Tests\Feature\Modules\Editorial\Events;

use App\Application\Services\EventBusService;
use App\Application\Services\FeatureFlagService;
use App\Application\Services\JobEngineService;
use App\Domain\Shared\Enums\FeatureFlagScope;
use App\Domain\Shared\Enums\PlatformJobStatus;
use App\Infrastructure\Persistence\Models\Project;
use App\Infrastructure\Persistence\Models\StoredEvent;
use App\Modules\Acquisition\Infrastructure\Persistence\Models\Source;
use App\Modules\Announcement\Infrastructure\Persistence\Models\Announcement;
use App\Modules\Editorial\Application\CapabilityGate;
use App\Modules\Editorial\Application\EditorialDiagnostics;
use App\Modules\Editorial\Application\GenerateArticlePreviewService;
use App\Modules\Editorial\Domain\Generation\AiProviderInterface;
use App\Modules\Editorial\Domain\GenerationRequest\GenerationRequest;
use App\Modules\Editorial\Domain\GenerationResult\EditorialErrorCodes;
use App\Modules\Editorial\Infrastructure\Jobs\GenerateArticlePreviewJob;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

class GenerationFailedEventOwnershipTest extends TestCase
{
    public function test_provider_logical_failure_emits_exactly_one_generation_failed(): void
    {
        [$organization, $project, $ann] = $this->seedEditorial();
        $this->bindFailingProvider();

        $before = StoredEvent::query()->where('event_type', 'editorial.generation_failed')->count();
        $out = app(GenerateArticlePreviewService::class)->generate($organization->id, $project->id, $ann->id);
        $this->assertFalse($out['ok']);
        $this->assertTrue($out['failure_event_emitted'] ?? false);
        $after = StoredEvent::query()->where('event_type', 'editorial.generation_failed')->count();
        $this->assertSame(1, $after - $before);
    }

    public function test_job_does_not_duplicate_generation_failed_for_handled_service_failure(): void
    {
        [$organization, $project, $ann] = $this->seedEditorial();
        $this->bindFailingProvider();

        $platformJob = $this->createPlatformJob($organization->id, $project->id, $ann->id, 'corr-one-fail');

        $before = StoredEvent::query()->where('event_type', 'editorial.generation_failed')->count();
        $job = new class($platformJob->id) extends GenerateArticlePreviewJob
        {
            public function attempts(): int
            {
                return 3;
            }
        };
        try {
            $job->handle(
                app(JobEngineService::class),
                app(GenerateArticlePreviewService::class),
                app(CapabilityGate::class),
                app(EventBusService::class),
            );
        } catch (\Throwable) {
        }
        $after = StoredEvent::query()->where('event_type', 'editorial.generation_failed')->count();
        $this->assertSame(1, $after - $before);
    }

    public function test_successful_generation_emits_zero_generation_failed(): void
    {
        [$organization, $project, $ann] = $this->seedEditorial();
        $before = StoredEvent::query()->where('event_type', 'editorial.generation_failed')->count();
        $out = app(GenerateArticlePreviewService::class)->generate($organization->id, $project->id, $ann->id);
        $this->assertTrue($out['ok']);
        $after = StoredEvent::query()->where('event_type', 'editorial.generation_failed')->count();
        $this->assertSame(0, $after - $before);
    }

    public function test_first_retryable_lock_failure_emits_zero_generation_failed(): void
    {
        [$organization, $project, $ann] = $this->seedEditorial();
        $platformJob = $this->createPlatformJob($organization->id, $project->id, $ann->id, 'corr-lock-1');

        $lock = Cache::lock("editorial:project:{$project->id}:announcement:{$ann->id}", 60);
        $this->assertTrue($lock->get());

        $before = StoredEvent::query()->where('event_type', 'editorial.generation_failed')->count();
        $job = new class($platformJob->id) extends GenerateArticlePreviewJob
        {
            public function attempts(): int
            {
                return 1;
            }
        };

        try {
            $job->handle(
                app(JobEngineService::class),
                app(GenerateArticlePreviewService::class),
                app(CapabilityGate::class),
                app(EventBusService::class),
            );
            $this->fail('expected lock failure');
        } catch (\Throwable $e) {
            $this->assertSame(EditorialErrorCodes::ANNOUNCEMENT_LOCKED, $e->getMessage());
        } finally {
            $lock->release();
        }

        $after = StoredEvent::query()->where('event_type', 'editorial.generation_failed')->count();
        $this->assertSame(0, $after - $before);
        $platformJob->refresh();
        $this->assertNotSame(PlatformJobStatus::Failed, $platformJob->status);
    }

    public function test_second_retryable_lock_failure_still_emits_zero_generation_failed(): void
    {
        [$organization, $project, $ann] = $this->seedEditorial();
        $platformJob = $this->createPlatformJob($organization->id, $project->id, $ann->id, 'corr-lock-2');

        $lock = Cache::lock("editorial:project:{$project->id}:announcement:{$ann->id}", 60);
        $this->assertTrue($lock->get());

        $before = StoredEvent::query()->where('event_type', 'editorial.generation_failed')->count();
        $job = new class($platformJob->id) extends GenerateArticlePreviewJob
        {
            public function attempts(): int
            {
                return 2;
            }
        };

        try {
            $job->handle(
                app(JobEngineService::class),
                app(GenerateArticlePreviewService::class),
                app(CapabilityGate::class),
                app(EventBusService::class),
            );
            $this->fail('expected lock failure');
        } catch (\Throwable $e) {
            $this->assertSame(EditorialErrorCodes::ANNOUNCEMENT_LOCKED, $e->getMessage());
        } finally {
            $lock->release();
        }

        $after = StoredEvent::query()->where('event_type', 'editorial.generation_failed')->count();
        $this->assertSame(0, $after - $before);
        $platformJob->refresh();
        $this->assertNotSame(PlatformJobStatus::Failed, $platformJob->status);
    }

    public function test_final_exhausted_retryable_lock_emits_exactly_one_generation_failed(): void
    {
        [$organization, $project, $ann] = $this->seedEditorial();
        $platformJob = $this->createPlatformJob($organization->id, $project->id, $ann->id, 'corr-lock-final');

        $lock = Cache::lock("editorial:project:{$project->id}:announcement:{$ann->id}", 60);
        $this->assertTrue($lock->get());

        $before = StoredEvent::query()->where('event_type', 'editorial.generation_failed')->count();
        $job = new class($platformJob->id) extends GenerateArticlePreviewJob
        {
            public function attempts(): int
            {
                return 3;
            }
        };

        try {
            $job->handle(
                app(JobEngineService::class),
                app(GenerateArticlePreviewService::class),
                app(CapabilityGate::class),
                app(EventBusService::class),
            );
            $this->fail('expected lock failure');
        } catch (\Throwable $e) {
            $this->assertSame(EditorialErrorCodes::ANNOUNCEMENT_LOCKED, $e->getMessage());
        } finally {
            $lock->release();
        }

        $after = StoredEvent::query()->where('event_type', 'editorial.generation_failed')->count();
        $this->assertSame(1, $after - $before);
        $platformJob->refresh();
        $this->assertSame(PlatformJobStatus::Failed, $platformJob->status);
        $this->assertNotSame(PlatformJobStatus::Running, $platformJob->status);
    }

    public function test_failed_hook_does_not_emit_second_terminal_event(): void
    {
        [$organization, $project, $ann] = $this->seedEditorial();
        $platformJob = $this->createPlatformJob($organization->id, $project->id, $ann->id, 'corr-failed-hook');

        $lock = Cache::lock("editorial:project:{$project->id}:announcement:{$ann->id}", 60);
        $this->assertTrue($lock->get());

        $before = StoredEvent::query()->where('event_type', 'editorial.generation_failed')->count();
        $job = new class($platformJob->id) extends GenerateArticlePreviewJob
        {
            public function attempts(): int
            {
                return 3;
            }
        };

        $exception = null;
        try {
            $job->handle(
                app(JobEngineService::class),
                app(GenerateArticlePreviewService::class),
                app(CapabilityGate::class),
                app(EventBusService::class),
            );
        } catch (\Throwable $e) {
            $exception = $e;
        } finally {
            $lock->release();
        }

        $mid = StoredEvent::query()->where('event_type', 'editorial.generation_failed')->count();
        $this->assertSame(1, $mid - $before);

        $job->failed($exception);
        $job->failed($exception);

        $after = StoredEvent::query()->where('event_type', 'editorial.generation_failed')->count();
        $this->assertSame(1, $after - $before);
    }

    public function test_reuse_does_not_create_false_new_completion(): void
    {
        [$organization, $project, $ann] = $this->seedEditorial();
        $diag = app(EditorialDiagnostics::class);
        $service = app(GenerateArticlePreviewService::class);
        $first = $service->generate($organization->id, $project->id, $ann->id);
        $this->assertTrue($first['ok']);
        $snap1 = $diag->snapshot($organization->id, $project->id);
        $this->assertSame(1, $snap1['generations_completed']);
        $this->assertSame(0, $snap1['generations_reused']);

        $second = $service->generate($organization->id, $project->id, $ann->id);
        $this->assertTrue($second['reused'] ?? false);
        $snap2 = $diag->snapshot($organization->id, $project->id);
        $this->assertSame(1, $snap2['generations_completed']);
        $this->assertSame(1, $snap2['generations_reused']);
        $this->assertTrue($snap2['preview_available']);
    }

    public function test_cross_project_diagnostics_remain_isolated(): void
    {
        [$organization, $projectA, $annA, $owner] = $this->seedEditorial();
        $projectB = Project::create([
            'organization_id' => $organization->id,
            'name' => 'Diag B',
            'slug' => 'diag-b-'.uniqid(),
            'created_by' => $owner->id,
        ]);
        $flags = app(FeatureFlagService::class);
        foreach ([CapabilityGate::EDITORIAL, CapabilityGate::EDITORIAL_GENERATION] as $key) {
            $flags->upsert($key, true, FeatureFlagScope::Project, null, $organization->id, $projectB->id);
        }
        $sourceB = Source::create([
            'organization_id' => $organization->id,
            'project_id' => $projectB->id,
            'slug' => 'diag-b-'.uniqid(),
            'name' => 'b',
            'source_type' => 'rss',
            'base_url' => 'https://example.com',
            'feed_url' => 'https://example.com/b-'.uniqid().'.xml',
            'feed_url_hash' => hash('sha256', uniqid('b', true)),
            'allowed_domains' => ['example.com'],
            'enabled' => true,
            'manual_only' => false,
            'acquire_interval_seconds' => 3600,
        ]);
        $annB = Announcement::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $organization->id,
            'project_id' => $projectB->id,
            'source_id' => $sourceB->id,
            'identity_hash' => hash('sha256', uniqid('idb', true)),
            'identity_basis' => 'canonical_url',
            'canonical_url' => 'https://example.com/b-'.uniqid(),
            'raw_title' => 'B',
            'content_hash' => hash('sha256', uniqid('cb', true)),
            'raw_payload' => ['title' => 'B', 'summary' => 'B'],
            'revision_no' => 1,
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        $diag = app(EditorialDiagnostics::class);
        app(GenerateArticlePreviewService::class)->generate($organization->id, $projectA->id, $annA->id);
        $this->assertSame(1, $diag->snapshot($organization->id, $projectA->id)['generations_completed']);
        $this->assertSame(0, $diag->snapshot($organization->id, $projectB->id)['generations_completed']);
        app(GenerateArticlePreviewService::class)->generate($organization->id, $projectB->id, $annB->id);
        $this->assertSame(1, $diag->snapshot($organization->id, $projectA->id)['generations_completed']);
        $this->assertSame(1, $diag->snapshot($organization->id, $projectB->id)['generations_completed']);
    }

    private function createPlatformJob(string $organizationId, string $projectId, string $announcementId, string $correlationId)
    {
        return app(JobEngineService::class)->create(
            'editorial.generate_article_preview',
            $organizationId,
            $projectId,
            [
                'organization_id' => $organizationId,
                'project_id' => $projectId,
                'announcement_id' => $announcementId,
                'correlation_id' => $correlationId,
            ]
        );
    }

    private function bindFailingProvider(): void
    {
        $this->app->bind(AiProviderInterface::class, fn () => new class implements AiProviderInterface
        {
            public function generate(GenerationRequest $request): array
            {
                return [
                    'ok' => false,
                    'provider_code' => 'stub.fail',
                    'execution_id' => 'exec_fail',
                    'duration_ms' => 1,
                    'error_code' => EditorialErrorCodes::PROVIDER_ERROR,
                    'error_message' => 'nope',
                ];
            }
        });
        $this->app->forgetInstance(GenerateArticlePreviewService::class);
        $this->app->forgetInstance(\App\Modules\Editorial\Application\GenerationOrchestrator::class);
    }

    /**
     * @return array{0: mixed, 1: Project, 2: Announcement, 3: mixed}
     */
    private function seedEditorial(): array
    {
        ['user' => $owner, 'organization' => $organization] = $this->createUserWithOrg();
        $project = Project::create([
            'organization_id' => $organization->id,
            'name' => 'Event Project',
            'slug' => 'event-'.uniqid(),
            'created_by' => $owner->id,
        ]);
        $source = Source::create([
            'organization_id' => $organization->id,
            'project_id' => $project->id,
            'slug' => 'event-src-'.uniqid(),
            'name' => 'event',
            'source_type' => 'rss',
            'base_url' => 'https://example.com',
            'feed_url' => 'https://example.com/e-'.uniqid().'.xml',
            'feed_url_hash' => hash('sha256', uniqid('e', true)),
            'allowed_domains' => ['example.com'],
            'enabled' => true,
            'manual_only' => false,
            'acquire_interval_seconds' => 3600,
        ]);
        $ann = Announcement::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $organization->id,
            'project_id' => $project->id,
            'source_id' => $source->id,
            'identity_hash' => hash('sha256', uniqid('ide', true)),
            'identity_basis' => 'canonical_url',
            'canonical_url' => 'https://example.com/e-'.uniqid(),
            'raw_title' => 'Event Title',
            'content_hash' => hash('sha256', uniqid('ce', true)),
            'raw_payload' => ['title' => 'Event Title', 'summary' => 'summary'],
            'revision_no' => 1,
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);
        $flags = app(FeatureFlagService::class);
        foreach ([CapabilityGate::EDITORIAL, CapabilityGate::EDITORIAL_GENERATION] as $key) {
            $flags->upsert($key, true, FeatureFlagScope::Project, null, $organization->id, $project->id);
        }

        return [$organization, $project, $ann, $owner];
    }
}
