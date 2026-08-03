<?php

namespace App\Domain\Events;

final class ProjectCreated implements DomainEvent
{
    public function __construct(
        public readonly string $projectId,
        public readonly string $organizationId,
        public readonly string $name,
        public readonly string $createdBy,
    ) {}

    public function eventName(): string
    {
        return 'project.created';
    }

    public function payload(): array
    {
        return [
            'project_id' => $this->projectId,
            'organization_id' => $this->organizationId,
            'name' => $this->name,
            'created_by' => $this->createdBy,
        ];
    }
}
