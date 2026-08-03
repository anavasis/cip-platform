<?php

namespace App\Modules\Acquisition\Domain\Events;

use App\Domain\Events\DomainEvent;

final readonly class AcquisitionRunFailed implements DomainEvent
{
    public function __construct(
        public string $organizationId,
        public string $projectId,
        public string $runId,
        public string $errorCode,
        public int $sourcesRequested = 0,
        public float $durationMs = 0.0,
    ) {}

    public function eventName(): string
    {
        return 'acquisition.run_failed';
    }

    public function payload(): array
    {
        return [
            'organization_id' => $this->organizationId,
            'project_id' => $this->projectId,
            'run_id' => $this->runId,
            'error_code' => $this->errorCode,
            'sources_requested' => $this->sourcesRequested,
            'duration_ms' => $this->durationMs,
        ];
    }
}
