<?php

namespace App\Domain\Events;

final class OrganizationCreated implements DomainEvent
{
    public function __construct(
        public readonly string $organizationId,
        public readonly string $name,
        public readonly string $createdBy,
    ) {}

    public function eventName(): string
    {
        return 'organization.created';
    }

    public function payload(): array
    {
        return [
            'organization_id' => $this->organizationId,
            'name' => $this->name,
            'created_by' => $this->createdBy,
        ];
    }
}
