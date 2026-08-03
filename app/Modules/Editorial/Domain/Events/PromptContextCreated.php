<?php

namespace App\Modules\Editorial\Domain\Events;

use App\Domain\Events\DomainEvent;

final readonly class PromptContextCreated implements DomainEvent
{
    public function __construct(
        public string $organizationId,
        public string $projectId,
        public ?string $contextId = null,
        public ?string $contextHash = null,
        public ?string $announcementId = null,
        public ?string $actorId = null,
        public ?string $correlationId = null,
    ) {}

    public function eventName(): string
    {
        return 'editorial.prompt_context_created';
    }

    public function payload(): array
    {
        return [
            'organization_id' => $this->organizationId,
            'project_id' => $this->projectId,
            'context_id' => $this->contextId,
            'context_hash' => $this->contextHash,
            'announcement_id' => $this->announcementId,
            'actor_id' => $this->actorId,
            'correlation_id' => $this->correlationId,
        ];
    }
}
