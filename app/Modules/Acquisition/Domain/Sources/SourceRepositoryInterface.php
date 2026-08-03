<?php

namespace App\Modules\Acquisition\Domain\Sources;

interface SourceRepositoryInterface
{
    /** @return array<int, array<string, mixed>> */
    public function findAll(string $organizationId, string $projectId): array;

    /** @return array<string, mixed>|null */
    public function findById(string $organizationId, string $projectId, string $id): ?array;

    /** @return array<int, array<string, mixed>> */
    public function findDue(string $organizationId, string $projectId): array;

    public function slugExists(
        string $organizationId,
        string $projectId,
        string $slug,
        ?string $excludeId = null,
    ): bool;

    public function feedHashExists(
        string $organizationId,
        string $projectId,
        string $hash,
        ?string $excludeId = null,
    ): bool;

    /**
     * Data must include organization_id and project_id.
     *
     * @param  array<string, mixed>  $data
     */
    public function insert(array $data): bool|string;

    /** @param array<string, mixed> $data */
    public function update(string $id, array $data): bool;

    public function setEnabled(
        string $organizationId,
        string $projectId,
        string $id,
        bool $enabled,
    ): bool;
}
