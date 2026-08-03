<?php

namespace App\Modules\Editorial\Domain\GenerationRequest;

/**
 * Persistence port for Generation Requests (tenant-scoped).
 */
interface GenerationRequestRepositoryInterface
{
    public function save(string $organizationId, string $projectId, GenerationRequest $request): bool;

    public function findById(string $organizationId, string $projectId, string $requestId): ?GenerationRequest;

    public function findByRequestHash(string $organizationId, string $projectId, string $requestHash): ?GenerationRequest;

    public function findLatestForAnnouncement(string $organizationId, string $projectId, string $announcementId): ?GenerationRequest;

    public function findLatestForPackage(string $organizationId, string $projectId, string $packageId): ?GenerationRequest;

    /**
     * @return list<GenerationRequest>
     */
    public function listForProject(string $organizationId, string $projectId, int $limit = 50, int $offset = 0): array;
}
