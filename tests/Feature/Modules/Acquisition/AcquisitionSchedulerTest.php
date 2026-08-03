<?php

namespace Tests\Feature\Modules\Acquisition;

use App\Application\Services\FeatureFlagService;
use App\Application\Services\JobEngineService;
use App\Application\Services\SchedulerService;
use App\Domain\Shared\Enums\FeatureFlagScope;
use App\Domain\Shared\Enums\PlatformJobStatus;
use App\Infrastructure\Persistence\Models\PlatformJob;
use App\Infrastructure\Persistence\Models\Project;
use App\Modules\Acquisition\Application\AcquisitionAwareSchedulerService;
use App\Modules\Acquisition\Application\AcquisitionScheduleRegistrar;
use App\Modules\Acquisition\Application\CapabilityGate;
use App\Modules\Acquisition\Application\SourceRegistryService;
use App\Modules\Acquisition\Infrastructure\Jobs\AcquireDueSourcesJob;
use App\Modules\Acquisition\Infrastructure\Jobs\AcquireSourceJob;
use App\Modules\Acquisition\Infrastructure\Persistence\Models\Source;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AcquisitionSchedulerTest extends TestCase
{
    public function test_kernel_scheduler_dispatches_acquisition_due_scan_adapter(): void
    {
        Queue::fake();
        ['user' => $owner, 'organization' => $organization] = $this->createUserWithOrg();
        $project = $this->createProject($organization->id, $owner->id, 'Scheduler Adapter');
        $scheduler = app(SchedulerService::class);
        $scheduler->create(
            $organization->id,
            'Due acquisition',
            '* * * * *',
            AcquisitionScheduleRegistrar::JOB_TYPE,
            $project->id,
            [
                'organization_id' => $organization->id,
                'project_id' => $project->id,
            ],
        );

        $this->assertInstanceOf(AcquisitionAwareSchedulerService::class, $scheduler);
        $this->assertSame(1, $scheduler->runDue());
        Queue::assertPushed(AcquireDueSourcesJob::class, 1);
        $this->assertDatabaseHas('platform_jobs', [
            'organization_id' => $organization->id,
            'project_id' => $project->id,
            'job_type' => AcquisitionScheduleRegistrar::JOB_TYPE,
        ]);
    }

    public function test_due_scan_dispatches_only_due_sources_from_its_project(): void
    {
        Queue::fake([AcquireSourceJob::class]);
        ['user' => $owner, 'organization' => $organization] = $this->createUserWithOrg();
        $project = $this->createProject($organization->id, $owner->id, 'Due Project');
        $otherProject = $this->createProject($organization->id, $owner->id, 'Other Project');
        $this->enableAcquisition($organization->id, $project->id);
        $due = $this->createSource($organization->id, $project->id, 'due', [
            'last_acquired_at' => now()->subHours(2),
            'last_checked_at' => now()->subHours(2),
        ]);
        $this->createSource($organization->id, $project->id, 'not-due', [
            'last_acquired_at' => now()->subHours(2),
            'last_checked_at' => now(),
        ]);
        $this->createSource($organization->id, $otherProject->id, 'other-project');
        $scan = $this->createScanJob($organization->id, $project->id);

        app()->call([new AcquireDueSourcesJob($scan->id), 'handle']);

        Queue::assertPushed(AcquireSourceJob::class, 1);
        Queue::assertPushed(AcquireSourceJob::class, function (AcquireSourceJob $queued) use ($due): bool {
            $child = PlatformJob::findOrFail($queued->platformJobId);

            return ($child->payload['source_id'] ?? null) === $due->id
                && ($child->payload['require_due'] ?? false) === true;
        });
        $scan->refresh();
        $this->assertSame(PlatformJobStatus::Completed, $scan->status);
        $this->assertSame(1, $scan->result['sources_due']);
        $this->assertSame(1, $scan->result['jobs_dispatched']);
    }

    public function test_due_scan_skips_when_project_scan_lock_is_held(): void
    {
        Queue::fake([AcquireSourceJob::class]);
        ['user' => $owner, 'organization' => $organization] = $this->createUserWithOrg();
        $project = $this->createProject($organization->id, $owner->id, 'Overlap Project');
        $this->enableAcquisition($organization->id, $project->id);
        $this->createSource($organization->id, $project->id, 'due');
        $scan = $this->createScanJob($organization->id, $project->id);
        $lock = Cache::lock("acquisition:due:{$project->id}", 300);
        $this->assertTrue($lock->get());

        try {
            app()->call([new AcquireDueSourcesJob($scan->id), 'handle']);
        } finally {
            $lock->release();
        }

        Queue::assertNotPushed(AcquireSourceJob::class);
        $this->assertTrue((bool) $scan->fresh()->result['overlap_skipped']);
    }

    public function test_due_scan_does_not_dispatch_source_with_active_source_lock(): void
    {
        Queue::fake([AcquireSourceJob::class]);
        ['user' => $owner, 'organization' => $organization] = $this->createUserWithOrg();
        $project = $this->createProject($organization->id, $owner->id, 'Source Lock Project');
        $this->enableAcquisition($organization->id, $project->id);
        $source = $this->createSource($organization->id, $project->id, 'locked');
        $scan = $this->createScanJob($organization->id, $project->id);
        $lock = Cache::lock(
            "acquisition:project:{$project->id}:source:{$source->id}",
            300,
        );
        $this->assertTrue($lock->get());

        try {
            app()->call([new AcquireDueSourcesJob($scan->id), 'handle']);
        } finally {
            $lock->release();
        }

        Queue::assertNotPushed(AcquireSourceJob::class);
        $scan->refresh();
        $this->assertSame(1, $scan->result['sources_due']);
        $this->assertSame(0, $scan->result['jobs_dispatched']);
    }

    public function test_due_scan_is_blocked_when_project_capability_is_disabled(): void
    {
        Queue::fake([AcquireSourceJob::class]);
        ['user' => $owner, 'organization' => $organization] = $this->createUserWithOrg();
        $project = $this->createProject($organization->id, $owner->id, 'Blocked Project');
        $this->createSource($organization->id, $project->id, 'due');
        $scan = $this->createScanJob($organization->id, $project->id);

        app()->call([new AcquireDueSourcesJob($scan->id), 'handle']);

        Queue::assertNotPushed(AcquireSourceJob::class);
        $scan->refresh();
        $this->assertSame(PlatformJobStatus::Completed, $scan->status);
        $this->assertFalse((bool) $scan->result['capability_enabled']);
    }

    public function test_enabling_source_registers_one_project_schedule(): void
    {
        ['user' => $owner, 'organization' => $organization] = $this->createUserWithOrg();
        $project = $this->createProject($organization->id, $owner->id, 'Registrar Project');
        $source = $this->createSource($organization->id, $project->id, 'disabled', [
            'enabled' => false,
        ]);
        $registry = app(SourceRegistryService::class);

        $this->assertTrue($registry->toggle(
            $organization->id,
            $project->id,
            $source->id,
            true,
        )['success']);
        $this->assertTrue($registry->toggle(
            $organization->id,
            $project->id,
            $source->id,
            true,
        )['success']);

        $this->assertDatabaseCount('schedule_definitions', 1);
        $this->assertDatabaseHas('schedule_definitions', [
            'organization_id' => $organization->id,
            'project_id' => $project->id,
            'job_type' => AcquisitionScheduleRegistrar::JOB_TYPE,
            'enabled' => true,
        ]);
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

    private function createScanJob(string $organizationId, string $projectId): PlatformJob
    {
        return app(JobEngineService::class)->create(
            AcquisitionScheduleRegistrar::JOB_TYPE,
            $organizationId,
            $projectId,
            [
                'organization_id' => $organizationId,
                'project_id' => $projectId,
            ],
        );
    }

    private function createProject(string $organizationId, string $userId, string $name): Project
    {
        return Project::create([
            'organization_id' => $organizationId,
            'name' => $name,
            'slug' => strtolower(str_replace(' ', '-', $name)).'-'.uniqid(),
            'created_by' => $userId,
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function createSource(
        string $organizationId,
        string $projectId,
        string $slug,
        array $overrides = [],
    ): Source {
        $feedUrl = "https://example.com/{$slug}.xml";

        return Source::create(array_merge([
            'organization_id' => $organizationId,
            'project_id' => $projectId,
            'slug' => $slug,
            'name' => $slug,
            'source_type' => 'rss',
            'base_url' => 'https://example.com',
            'feed_url' => $feedUrl,
            'feed_url_hash' => hash('sha256', $feedUrl),
            'allowed_domains' => ['example.com'],
            'enabled' => true,
            'manual_only' => false,
            'acquire_interval_seconds' => 3600,
        ], $overrides));
    }
}
