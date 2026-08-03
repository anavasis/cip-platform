<?php

namespace App\Modules\Editorial\Domain\Events;

use App\Domain\Events\DomainEvent;

final readonly class ArticlePreviewCreated implements DomainEvent
{
    public function __construct(
        public string $organizationId,
        public string $projectId,
        public ?string $previewId = null,
        public ?string $resultId = null,
        public ?string $announcementId = null,
        public ?string $requestId = null,
        public ?string $actorId = null,
        public ?string $correlationId = null,
    ) {}

    public function eventName(): string
    {
        return 'editorial.article_preview_created';
    }

    public function payload(): array
    {
        return [
            'organization_id' => $this->organizationId,
            'project_id' => $this->projectId,
            'preview_id' => $this->previewId,
            'result_id' => $this->resultId,
            'announcement_id' => $this->announcementId,
            'request_id' => $this->requestId,
            'actor_id' => $this->actorId,
            'correlation_id' => $this->correlationId,
        ];
    }
}
