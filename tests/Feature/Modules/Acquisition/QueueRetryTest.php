<?php

namespace Tests\Feature\Modules\Acquisition;

use App\Application\Services\FeatureFlagService;
use App\Application\Services\JobEngineService;
use App\Domain\Shared\Enums\FeatureFlagScope;
use App\Domain\Shared\Enums\PlatformJobStatus;
use App\Infrastructure\Persistence\Models\Project;
use App\Modules\Acquisition\Application\CapabilityGate;
use App\Modules\Acquisition\Infrastructure\Jobs\AcquireSourceJob;
use Illuminate\Support\Str;
use Tests\TestCase;

class QueueRetryTest extends TestCase
{
    public function test_missing_source_failure_marks_platform_job_failed_cleanly(): void
    {
        ['user' => $owner, 'organization' => $organization] = $this->createUserWithOrg();
        $project = Project::create([
            'organization_id' => $organization->id,
            'name' => 'Retry Project',
            'slug' => 'retry-project',
            'created_by' => $owner->id,
        ]);
        $flags = app(FeatureFlagService::class);

        foreach ([CapabilityGate::ACQUISITION, CapabilityGate::SOURCE_REGISTRY] as $key) {
            $flags->upsert(
                $key,
                true,
                FeatureFlagScope::Project,
                null,
                $organization->id,
                $project->id,
            );
        }

        $missingSourceId = (string) Str::uuid();
        $job = app(JobEngineService::class)->create(
            'acquisition.acquire_source',
            $organization->id,
            $project->id,
            [
                'organization_id' => $organization->id,
                'project_id' => $project->id,
                'source_id' => $missingSourceId,
                'trigger' => 'retry-test',
            ],
        );

        AcquireSourceJob::dispatch($job->id);
        $job->refresh();

        $this->assertSame(PlatformJobStatus::Failed, $job->status);
        $this->assertSame('acquisition_failed', $job->error);
        $this->assertNotNull($job->started_at);
        $this->assertNotNull($job->completed_at);
        $this->assertDatabaseHas('acquisition_runs', [
            'organization_id' => $organization->id,
            'project_id' => $project->id,
            'status' => 'failed',
            'sources_requested' => 1,
            'sources_succeeded' => 0,
            'sources_failed' => 1,
        ]);
        $this->assertDatabaseHas('acquisition_run_items', [
            'organization_id' => $organization->id,
            'project_id' => $project->id,
            'source_id' => $missingSourceId,
            'success' => false,
            'error_code' => 'not_found',
        ]);
    }
}
