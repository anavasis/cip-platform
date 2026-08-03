<?php

namespace App\Modules\Acquisition\Domain\Events;

use App\Domain\Events\DomainEvent;

final readonly class SourceCreated implements DomainEvent
{
    public function __construct(
        public string $organizationId,
        public string $projectId,
        public string $sourceId,
        public string $slug,
        public string $sourceType,
    ) {}

    public function eventName(): string
    {
        return 'source.created';
    }

    public function payload(): array
    {
        return [
            'organization_id' => $this->organizationId,
            'project_id' => $this->projectId,
            'source_id' => $this->sourceId,
            'slug' => $this->slug,
            'source_type' => $this->sourceType,
        ];
    }
}
