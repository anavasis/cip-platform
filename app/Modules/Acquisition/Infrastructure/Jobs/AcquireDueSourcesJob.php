<?php

namespace App\Modules\Acquisition\Infrastructure\Jobs;

use App\Application\Services\JobEngineService;
use App\Infrastructure\Persistence\Models\PlatformJob;
use App\Modules\Acquisition\Infrastructure\Persistence\Models\Source;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class AcquireDueSourcesJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly ?string $platformJobId = null) {}

    public function handle(JobEngineService $jobEngine): void
    {
        $platformJob = $this->platformJobId !== null
            ? PlatformJob::findOrFail($this->platformJobId)
            : null;

        if ($platformJob !== null) {
            $platformJob = $jobEngine->markRunning($platformJob);
        }

        try {
            $dueSources = Source::query()
                ->where('enabled', true)
                ->where('manual_only', false)
                ->orderBy('organization_id')
                ->orderBy('project_id')
                ->orderBy('last_checked_at')
                ->get(['id', 'organization_id', 'project_id']);
            $dispatchedIds = [];

            foreach ($dueSources as $source) {
                $job = $jobEngine->create(
                    'acquisition.acquire_source',
                    (string) $source->organization_id,
                    (string) $source->project_id,
                    [
                        'organization_id' => (string) $source->organization_id,
                        'project_id' => (string) $source->project_id,
                        'source_id' => (string) $source->id,
                        'trigger' => 'schedule',
                    ],
                );
                AcquireSourceJob::dispatch($job->id);
                $dispatchedIds[] = $job->id;
            }

            if ($platformJob !== null) {
                $jobEngine->markCompleted($platformJob, [
                    'sources_due' => $dueSources->count(),
                    'jobs_dispatched' => count($dispatchedIds),
                    'platform_job_ids' => $dispatchedIds,
                ]);
            }
        } catch (Throwable $throwable) {
            if ($platformJob !== null) {
                $jobEngine->markFailed(
                    $platformJob,
                    $throwable->getMessage() !== '' ? $throwable->getMessage() : 'due_source_dispatch_failed',
                );
            }
        }
    }
}
