<?php

namespace App\Modules\Acquisition\Domain\Collectors;

interface CollectorInterface
{
    public function id(): string;

    /**
     * @param  array<int, string>  $allowedDomains
     * @return array<string, mixed>
     */
    public function collect(string $url, array $allowedDomains): array;
}
