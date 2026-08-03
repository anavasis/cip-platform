<?php

namespace App\Application\Services;

use App\Domain\Shared\Enums\PlatformJobStatus;
use App\Infrastructure\Persistence\Models\PlatformJob;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class JobEngineService
{
    /**
     * @param  array<string, mixed>|null  $payload
     */
    public function create(
        string $jobType,
        ?string $organizationId = null,
        ?string $projectId = null,
        ?array $payload = null,
    ): PlatformJob {
        return PlatformJob::create([
            'organization_id' => $organizationId,
            'project_id' => $projectId,
            'job_type' => $jobType,
            'status' => PlatformJobStatus::Pending,
            'payload' => $payload,
        ]);
    }

    public function markRunning(PlatformJob $job): PlatformJob
    {
        $job->update([
            'status' => PlatformJobStatus::Running,
            'started_at' => now(),
        ]);

        return $job->fresh();
    }

    /**
     * @param  array<string, mixed>|null  $result
     */
    public function markCompleted(PlatformJob $job, ?array $result = null): PlatformJob
    {
        $job->update([
            'status' => PlatformJobStatus::Completed,
            'result' => $result,
            'completed_at' => now(),
        ]);

        return $job->fresh();
    }

    public function markFailed(PlatformJob $job, string $error): PlatformJob
    {
        $job->update([
            'status' => PlatformJobStatus::Failed,
            'error' => $error,
            'completed_at' => now(),
        ]);

        return $job->fresh();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $query = PlatformJob::query()->orderByDesc('created_at');

        if (! empty($filters['organization_id'])) {
            $query->where('organization_id', $filters['organization_id']);
        }

        if (! empty($filters['project_id'])) {
            $query->where('project_id', $filters['project_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->paginate($perPage);
    }
}
