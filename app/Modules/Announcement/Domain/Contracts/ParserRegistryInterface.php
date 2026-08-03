<?php

namespace App\Modules\Announcement\Domain\Contracts;

interface ParserRegistryInterface
{
    /**
     * @return array<int|string, mixed>
     */
    public function all(): array;
}
