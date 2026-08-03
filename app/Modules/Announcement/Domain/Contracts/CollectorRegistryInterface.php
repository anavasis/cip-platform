<?php

namespace App\Modules\Announcement\Domain\Contracts;

interface CollectorRegistryInterface
{
    public function has(string $collectorId): bool;

    /**
     * @return array<string, string>
     */
    public function sourceTypeMap(): array;
}
