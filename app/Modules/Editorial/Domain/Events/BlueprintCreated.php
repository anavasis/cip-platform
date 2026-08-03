<?php

namespace App\Modules\Editorial\Domain\Events;

use App\Domain\Events\DomainEvent;

final readonly class BlueprintCreated implements DomainEvent
{
    public function __construct(
        public string $organizationId,
        public string $projectId,
        public ?string $blueprintId = null,
        public ?string $announcementId = null,
        public ?string $actorId = null,
        public ?string $correlationId = null,
    ) {}

    public function eventName(): string
    {
        return 'editorial.blueprint_created';
    }

    public function payload(): array
    {
        return [
            'organization_id' => $this->organizationId,
            'project_id' => $this->projectId,
            'blueprint_id' => $this->blueprintId,
            'announcement_id' => $this->announcementId,
            'actor_id' => $this->actorId,
            'correlation_id' => $this->correlationId,
        ];
    }
}
