<?php

namespace App\Modules\Acquisition\Domain\Events;

use App\Domain\Events\DomainEvent;

final readonly class AcquisitionRunStarted implements DomainEvent
{
    public function __construct(
        public string $organizationId,
        public string $projectId,
        public string $runId,
        public int $sourcesRequested,
    ) {}

    public function eventName(): string
    {
        return 'acquisition.run_started';
    }

    public function payload(): array
    {
        return [
            'organization_id' => $this->organizationId,
            'project_id' => $this->projectId,
            'run_id' => $this->runId,
            'sources_requested' => $this->sourcesRequested,
        ];
    }
}
