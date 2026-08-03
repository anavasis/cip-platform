<?php

namespace App\Application\Services;

use App\Infrastructure\Jobs\PingJob;
use App\Infrastructure\Persistence\Models\ScheduleDefinition;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;

class SchedulerService
{
    public function __construct(
        private readonly JobEngineService $jobEngine,
    ) {}

    /**
     * @param  array<string, mixed>|null  $payload
     */
    public function create(
        string $organizationId,
        string $name,
        string $cronExpression,
        string $jobType,
        ?string $projectId = null,
        ?array $payload = null,
    ): ScheduleDefinition {
        return ScheduleDefinition::create([
            'organization_id' => $organizationId,
            'project_id' => $projectId,
            'name' => $name,
            'cron_expression' => $cronExpression,
            'job_type' => $jobType,
            'payload' => $payload,
            'enabled' => true,
            'next_run_at' => now(),
        ]);
    }

    /**
     * @return Collection<int, ScheduleDefinition>
     */
    public function listDue(): Collection
    {
        return ScheduleDefinition::query()
            ->where('enabled', true)
            ->where(function ($q) {
                $q->whereNull('next_run_at')
                    ->orWhere('next_run_at', '<=', now());
            })
            ->get();
    }

    public function runDue(): int
    {
        $count = 0;

        foreach ($this->listDue() as $schedule) {
            $platformJob = $this->jobEngine->create(
                $schedule->job_type,
                $schedule->organization_id,
                $schedule->project_id,
                $schedule->payload,
            );

            PingJob::dispatch($platformJob->id);

            $schedule->update([
                'last_run_at' => now(),
                'next_run_at' => now()->addMinute(),
            ]);

            $count++;
        }

        return $count;
    }

    public function runDueCommand(): int
    {
        return $this->runDue();
    }
}
