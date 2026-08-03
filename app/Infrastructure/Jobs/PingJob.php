<?php

namespace App\Infrastructure\Jobs;

use App\Application\Services\EventBusService;
use App\Application\Services\JobEngineService;
use App\Domain\Events\PlatformJobCompleted;
use App\Infrastructure\Persistence\Models\PlatformJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class PingJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $platformJobId,
    ) {}

    public function handle(JobEngineService $jobEngine, EventBusService $eventBus): void
    {
        $job = PlatformJob::findOrFail($this->platformJobId);
        $jobEngine->markRunning($job);

        $result = ['message' => 'pong', 'timestamp' => now()->toIso8601String()];
        $job = $jobEngine->markCompleted($job, $result);

        $eventBus->dispatch(new PlatformJobCompleted(
            $job->id,
            $job->job_type,
            $job->status->value,
        ));
    }
}
