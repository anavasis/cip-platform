<?php

namespace App\Modules\Editorial\Domain\PromptContext;

/**
 * Persistence port for Prompt Context snapshots (tenant-scoped).
 */
interface PromptContextRepositoryInterface
{
    public function save(string $organizationId, string $projectId, PromptContext $context): bool;

    public function findById(string $organizationId, string $projectId, string $contextId): ?PromptContext;

    public function findByContextHash(string $organizationId, string $projectId, string $contextHash): ?PromptContext;

    public function findLatestForAnnouncement(string $organizationId, string $projectId, string $announcementId): ?PromptContext;

    public function findLatestForBlueprint(string $organizationId, string $projectId, string $blueprintId): ?PromptContext;
}
