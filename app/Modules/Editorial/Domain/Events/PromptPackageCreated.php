<?php

namespace App\Modules\Editorial\Domain\Events;

use App\Domain\Events\DomainEvent;

final readonly class PromptPackageCreated implements DomainEvent
{
    public function __construct(
        public string $organizationId,
        public string $projectId,
        public ?string $packageId = null,
        public ?string $packageHash = null,
        public ?string $announcementId = null,
        public ?string $actorId = null,
        public ?string $correlationId = null,
    ) {}

    public function eventName(): string
    {
        return 'editorial.prompt_package_created';
    }

    public function payload(): array
    {
        return [
            'organization_id' => $this->organizationId,
            'project_id' => $this->projectId,
            'package_id' => $this->packageId,
            'package_hash' => $this->packageHash,
            'announcement_id' => $this->announcementId,
            'actor_id' => $this->actorId,
            'correlation_id' => $this->correlationId,
        ];
    }
}
