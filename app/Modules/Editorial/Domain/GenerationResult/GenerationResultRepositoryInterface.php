<?php

namespace App\Modules\Editorial\Domain\GenerationResult;

/**
 * Persistence port for Generation Results (tenant-scoped).
 */
interface GenerationResultRepositoryInterface
{
    public function save(string $organizationId, string $projectId, GenerationResult $result): bool;

    public function findById(string $organizationId, string $projectId, string $resultId): ?GenerationResult;

    public function findByResultHash(string $organizationId, string $projectId, string $resultHash): ?GenerationResult;

    public function findByRequestId(string $organizationId, string $projectId, string $requestId): ?GenerationResult;

    public function findLatestForAnnouncement(string $organizationId, string $projectId, string $announcementId): ?GenerationResult;
}
