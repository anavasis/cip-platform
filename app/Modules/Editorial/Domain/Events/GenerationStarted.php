<?php

namespace App\Modules\Editorial\Domain\Events;

use App\Domain\Events\DomainEvent;

final readonly class GenerationStarted implements DomainEvent
{
    public function __construct(
        public string $organizationId,
        public string $projectId,
        public ?string $requestId = null,
        public ?string $announcementId = null,
        public ?string $actorId = null,
        public ?string $correlationId = null,
    ) {}

    public function eventName(): string
    {
        return 'editorial.generation_started';
    }

    public function payload(): array
    {
        return [
            'organization_id' => $this->organizationId,
            'project_id' => $this->projectId,
            'request_id' => $this->requestId,
            'announcement_id' => $this->announcementId,
            'actor_id' => $this->actorId,
            'correlation_id' => $this->correlationId,
        ];
    }
}
