<?php

namespace App\Modules\Acquisition\Domain\Events;

use App\Domain\Events\DomainEvent;

final readonly class AcquisitionRunCompleted implements DomainEvent
{
    public function __construct(
        public string $organizationId,
        public string $projectId,
        public string $runId,
        public int $sourcesRequested,
        public int $sourcesSucceeded,
        public int $sourcesFailed,
        public float $durationMs,
    ) {}

    public function eventName(): string
    {
        return 'acquisition.run_completed';
    }

    public function payload(): array
    {
        return [
            'organization_id' => $this->organizationId,
            'project_id' => $this->projectId,
            'run_id' => $this->runId,
            'sources_requested' => $this->sourcesRequested,
            'sources_succeeded' => $this->sourcesSucceeded,
            'sources_failed' => $this->sourcesFailed,
            'duration_ms' => $this->durationMs,
        ];
    }
}
