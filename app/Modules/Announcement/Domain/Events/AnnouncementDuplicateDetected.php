<?php

namespace App\Modules\Announcement\Domain\Events;

use App\Domain\Events\DomainEvent;

final class AnnouncementDuplicateDetected implements DomainEvent
{
    public function __construct(
        public readonly string $organizationId,
        public readonly string $projectId,
        public readonly string $sourceId,
        public readonly string $identityHash,
    ) {}

    public function eventName(): string
    {
        return 'announcement.duplicate_detected';
    }

    public function payload(): array
    {
        return [
            'organization_id' => $this->organizationId,
            'project_id' => $this->projectId,
            'source_id' => $this->sourceId,
            'identity_hash' => $this->identityHash,
        ];
    }
}
