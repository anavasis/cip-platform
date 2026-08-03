<?php

namespace App\Modules\Editorial\Domain\Blueprint;

/**
 * Persistence port for Content Blueprints (tenant-scoped).
 */
interface ContentBlueprintRepositoryInterface
{
    public function save(string $organizationId, string $projectId, ContentBlueprint $blueprint): bool;

    public function findById(string $organizationId, string $projectId, string $blueprintId): ?ContentBlueprint;

    public function findLatestForAnnouncement(string $organizationId, string $projectId, string $announcementId): ?ContentBlueprint;
}
