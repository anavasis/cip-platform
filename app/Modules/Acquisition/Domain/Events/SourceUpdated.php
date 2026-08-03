<?php

namespace App\Modules\Acquisition\Domain\Events;

use App\Domain\Events\DomainEvent;

final readonly class SourceUpdated implements DomainEvent
{
    /**
     * @param  array<int, string>  $changedFields
     */
    public function __construct(
        public string $organizationId,
        public string $projectId,
        public string $sourceId,
        public array $changedFields = [],
    ) {}

    public function eventName(): string
    {
        return 'source.updated';
    }

    public function payload(): array
    {
        return [
            'organization_id' => $this->organizationId,
            'project_id' => $this->projectId,
            'source_id' => $this->sourceId,
            'changed_fields' => array_values($this->changedFields),
        ];
    }
}
