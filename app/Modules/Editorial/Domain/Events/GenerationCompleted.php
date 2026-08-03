<?php

namespace App\Modules\Editorial\Domain\Events;

use App\Domain\Events\DomainEvent;

final readonly class GenerationCompleted implements DomainEvent
{
    public function __construct(
        public string $organizationId,
        public string $projectId,
        public ?string $requestId = null,
        public ?string $resultId = null,
        public ?string $resultHash = null,
        public ?string $announcementId = null,
        public ?string $previewId = null,
        public ?string $actorId = null,
        public ?string $correlationId = null,
    ) {}

    public function eventName(): string
    {
        return 'editorial.generation_completed';
    }

    public function payload(): array
    {
        return [
            'organization_id' => $this->organizationId,
            'project_id' => $this->projectId,
            'request_id' => $this->requestId,
            'result_id' => $this->resultId,
            'result_hash' => $this->resultHash,
            'announcement_id' => $this->announcementId,
            'preview_id' => $this->previewId,
            'actor_id' => $this->actorId,
            'correlation_id' => $this->correlationId,
        ];
    }
}
