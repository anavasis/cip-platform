<?php

namespace App\Modules\Acquisition\Application;

use App\Application\Services\SchedulerService;
use App\Infrastructure\Persistence\Models\ScheduleDefinition;

final readonly class AcquisitionScheduleRegistrar
{
    public const JOB_TYPE = 'acquisition.acquire_due_sources';

    public function __construct(
        private SchedulerService $scheduler,
    ) {}

    public function ensureForProject(string $organizationId, string $projectId): ScheduleDefinition
    {
        $existing = ScheduleDefinition::query()
            ->where('organization_id', $organizationId)
            ->where('project_id', $projectId)
            ->where('job_type', self::JOB_TYPE)
            ->first();
        $payload = [
            'organization_id' => $organizationId,
            'project_id' => $projectId,
        ];

        if ($existing !== null) {
            $existing->update([
                'enabled' => true,
                'payload' => $payload,
            ]);

            return $existing->fresh();
        }

        return $this->scheduler->create(
            $organizationId,
            'Acquisition due source scan',
            '* * * * *',
            self::JOB_TYPE,
            $projectId,
            $payload,
        );
    }
}
