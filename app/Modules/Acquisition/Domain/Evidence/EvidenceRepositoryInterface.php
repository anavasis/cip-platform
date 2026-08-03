<?php

namespace App\Modules\Acquisition\Domain\Evidence;

interface EvidenceRepositoryInterface
{
    public function store(Evidence $evidence): string;

    public function find(string $key): ?Evidence;

    public function count(): int;

    /** @return array<string, Evidence> */
    public function all(): array;

    public function storeOperations(): int;

    /** @return array<int, array<string, mixed>> */
    public function summaries(): array;
}
