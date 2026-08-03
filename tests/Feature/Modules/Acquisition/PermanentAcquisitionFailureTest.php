<?php

namespace Tests\Feature\Modules\Acquisition;

use App\Application\Services\FeatureFlagService;
use App\Application\Services\JobEngineService;
use App\Domain\Shared\Enums\FeatureFlagScope;
use App\Domain\Shared\Enums\PlatformJobStatus;
use App\Infrastructure\Persistence\Models\Project;
use App\Infrastructure\Persistence\Models\StoredEvent;
use App\Modules\Acquisition\Application\CapabilityGate;
use App\Modules\Acquisition\Infrastructure\Jobs\AcquireSourceJob;
use Illuminate\Support\Str;
use Tests\TestCase;

class PermanentAcquisitionFailureTest extends TestCase
{
    public function test_missing_source_is_terminalized_without_retry_exception(): void
    {
        ['user' => $owner, 'organization' => $organization] = $this->createUserWithOrg();
        $project = Project::create([
            'organization_id' => $organization->id,
            'name' => 'Permanent Failure Project',
            'slug' => 'permanent-failure-project',
            'created_by' => $owner->id,
        ]);
        $this->enableAcquisition($organization->id, $project->id);
        $missingSourceId = (string) Str::uuid();
        $job = app(JobEngineService::class)->create(
            'acquisition.acquire_source',
            $organization->id,
            $project->id,
            [
                'organization_id' => $organization->id,
                'project_id' => $project->id,
                'source_id' => $missingSourceId,
                'trigger' => 'permanent-failure-test',
            ],
        );

        AcquireSourceJob::dispatch($job->id);
        $job->refresh();

        $this->assertSame(PlatformJobStatus::Failed, $job->status);
        $this->assertSame('not_found', $job->error);
        $this->assertNotNull($job->started_at);
        $this->assertNotNull($job->completed_at);
        $this->assertDatabaseHas('acquisition_runs', [
            'organization_id' => $organization->id,
            'project_id' => $project->id,
            'status' => 'failed',
            'error_code' => 'not_found',
            'sources_requested' => 1,
            'sources_succeeded' => 0,
            'sources_failed' => 1,
        ]);
        $this->assertDatabaseHas('acquisition_run_items', [
            'organization_id' => $organization->id,
            'project_id' => $project->id,
            'source_id' => null,
            'success' => false,
            'error_code' => 'not_found',
        ]);
        $this->assertSame(1, StoredEvent::query()
            ->where('event_type', 'acquisition.run_started')
            ->count());
        $this->assertSame(1, StoredEvent::query()
            ->where('event_type', 'acquisition.run_failed')
            ->count());
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
