<?php

namespace App\Modules\Announcement\Domain\Events;

use App\Domain\Events\DomainEvent;

final class AnnouncementUnchanged implements DomainEvent
{
    public function __construct(
        public readonly string $organizationId,
        public readonly string $projectId,
        public readonly string $sourceId,
        public readonly string $announcementId,
        public readonly string $identityHash,
    ) {}

    public function eventName(): string
    {
        return 'announcement.unchanged';
    }

    public function payload(): array
    {
        return [
            'organization_id' => $this->organizationId,
            'project_id' => $this->projectId,
            'source_id' => $this->sourceId,
            'announcement_id' => $this->announcementId,
            'identity_hash' => $this->identityHash,
        ];
    }
}
