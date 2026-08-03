<?php

namespace App\Modules\Acquisition\Infrastructure\Evidence;

use App\Modules\Acquisition\Domain\Evidence\Evidence;
use App\Modules\Acquisition\Domain\Evidence\EvidenceRepositoryInterface;

/**
 * Request-scoped evidence store. No database or disk writes.
 */
final class InMemoryEvidenceRepository implements EvidenceRepositoryInterface
{
    /** @var array<string, Evidence> */
    private array $items = [];

    private int $storeOperations = 0;

    public function store(Evidence $evidence): string
    {
        $key = $this->resolveStorageKey($evidence);
        $this->items[$key] = $evidence;
        $this->storeOperations++;

        return $key;
    }

    public function find(string $key): ?Evidence
    {
        return $this->items[$key] ?? null;
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function all(): array
    {
        return $this->items;
    }

    public function storeOperations(): int
    {
        return $this->storeOperations;
    }

    public function summaries(): array
    {
        $summaries = [];

        foreach ($this->items as $key => $evidence) {
            $summary = $evidence->toMetadataArray();
            $summary['storage_key'] = $key;
            $summaries[] = $summary;
        }

        return $summaries;
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
