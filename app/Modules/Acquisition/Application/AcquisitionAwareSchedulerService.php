<?php

namespace App\Modules\Acquisition\Application;

use App\Application\Services\JobEngineService;
use App\Application\Services\SchedulerService;
use App\Infrastructure\Jobs\PingJob;
use App\Modules\Acquisition\Infrastructure\Jobs\AcquireDueSourcesJob;
use App\Modules\Acquisition\Infrastructure\Jobs\AcquireSourceJob;
use App\Modules\Acquisition\Infrastructure\Jobs\IngestSourceJob;
use App\Modules\Acquisition\Infrastructure\Jobs\SourceConnectivityCheckJob;

class AcquisitionAwareSchedulerService extends SchedulerService
{
    public function __construct(
        private readonly JobEngineService $moduleJobEngine,
    ) {
        parent::__construct($moduleJobEngine);
    }

    public function runDue(): int
    {
        $count = 0;

        foreach ($this->listDue() as $schedule) {
            $platformJob = $this->moduleJobEngine->create(
                $schedule->job_type,
                $schedule->organization_id,
                $schedule->project_id,
                $schedule->payload,
            );

            match ($schedule->job_type) {
                'acquisition.acquire_due_sources' => AcquireDueSourcesJob::dispatch($platformJob->id),
                'acquisition.acquire_source' => AcquireSourceJob::dispatch($platformJob->id),
                'acquisition.ingest_source' => IngestSourceJob::dispatch($platformJob->id),
                'acquisition.source_check' => SourceConnectivityCheckJob::dispatch($platformJob->id),
                default => PingJob::dispatch($platformJob->id),
            };

            $schedule->update([
                'last_run_at' => now(),
                'next_run_at' => now()->addMinute(),
            ]);
            $count++;
        }

        return $count;
    }
}
