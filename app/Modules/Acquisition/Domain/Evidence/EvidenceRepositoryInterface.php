<?php

namespace App\Modules\Acquisition\Domain\Evidence;

interface EvidenceRepositoryInterface
{
    public function store(Evidence $evidence): string;

    public function find(string $organizationId, string $projectId, string $key): ?Evidence;

    public function count(string $organizationId, string $projectId): int;

    /** @return array<string, Evidence> */
    public function all(string $organizationId, string $projectId): array;

    public function storeOperations(string $organizationId, string $projectId): int;

    /** @return array<int, array<string, mixed>> */
    public function summaries(string $organizationId, string $projectId): array;
}
