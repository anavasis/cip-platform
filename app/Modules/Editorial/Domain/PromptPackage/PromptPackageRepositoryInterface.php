<?php

namespace App\Modules\Editorial\Domain\PromptPackage;

/**
 * Persistence port for Prompt Packages (tenant-scoped).
 */
interface PromptPackageRepositoryInterface
{
    public function save(string $organizationId, string $projectId, PromptPackage $package): bool;

    public function findById(string $organizationId, string $projectId, string $packageId): ?PromptPackage;

    public function findByPackageHash(string $organizationId, string $projectId, string $packageHash): ?PromptPackage;

    public function findLatestForAnnouncement(string $organizationId, string $projectId, string $announcementId): ?PromptPackage;

    public function findLatestForContext(string $organizationId, string $projectId, string $contextId): ?PromptPackage;
}
