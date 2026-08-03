<?php

namespace App\Modules\Acquisition\Domain\Events;

use App\Domain\Events\DomainEvent;

final readonly class SourceEnabled implements DomainEvent
{
    public function __construct(
        public string $organizationId,
        public string $projectId,
        public string $sourceId,
    ) {}

    public function eventName(): string
    {
        return 'source.enabled';
    }

    public function payload(): array
    {
        return [
            'organization_id' => $this->organizationId,
            'project_id' => $this->projectId,
            'source_id' => $this->sourceId,
        ];
    }
}
