<?php

namespace App\Modules\Acquisition\Infrastructure\Evidence;

use App\Modules\Acquisition\Domain\Evidence\Evidence;
use App\Modules\Acquisition\Domain\Evidence\EvidenceRepositoryInterface;
use InvalidArgumentException;

/**
 * Request-scoped evidence store. No database or disk writes.
 */
final class InMemoryEvidenceRepository implements EvidenceRepositoryInterface
{
    /** @var array<string, array<string, Evidence>> */
    private array $items = [];

    /** @var array<string, int> */
    private array $storeOperations = [];

    public function store(Evidence $evidence): string
    {
        $partition = $this->partitionKey($evidence->organizationId(), $evidence->projectId());
        $key = $this->resolveStorageKey($evidence);
        $this->items[$partition][$key] = $evidence;
        $this->storeOperations[$partition] = ($this->storeOperations[$partition] ?? 0) + 1;

        return $key;
    }

    public function find(string $organizationId, string $projectId, string $key): ?Evidence
    {
        return $this->items[$this->partitionKey($organizationId, $projectId)][$key] ?? null;
    }

    public function count(string $organizationId, string $projectId): int
    {
        return count($this->all($organizationId, $projectId));
    }

    public function all(string $organizationId, string $projectId): array
    {
        return $this->items[$this->partitionKey($organizationId, $projectId)] ?? [];
    }

    public function storeOperations(string $organizationId, string $projectId): int
    {
        return $this->storeOperations[$this->partitionKey($organizationId, $projectId)] ?? 0;
    }

    public function summaries(string $organizationId, string $projectId): array
    {
        $summaries = [];

        foreach ($this->all($organizationId, $projectId) as $key => $evidence) {
            $summary = $evidence->toMetadataArray();
            $summary['storage_key'] = $key;
            $summaries[] = $summary;
        }

        return $summaries;
    }

    private function partitionKey(string $organizationId, string $projectId): string
    {
        $organizationId = trim($organizationId);
        $projectId = trim($projectId);

        if ($organizationId === '' || $projectId === '') {
            throw new InvalidArgumentException('Evidence tenant context is required.');
        }

        return $organizationId."\0".$projectId;
    }

    private function resolveStorageKey(Evidence $evidence): string
    {
        $key = $evidence->contentHash();

        if ($key === '') {
            $key = $evidence->bodyHash();
        }

        return $key !== ''
            ? $key
            : hash('sha256', $evidence->url().'|'.$evidence->fetchedAt());
    }
}
