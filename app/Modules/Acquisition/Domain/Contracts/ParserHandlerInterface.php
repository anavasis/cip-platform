<?php

namespace App\Modules\Acquisition\Domain\Contracts;

interface ParserHandlerInterface
{
    public function supports(string $sourceType, string $parserProfile): bool;

    /**
     * @param  array<int, string>  $allowedDomains
     * @return array<string, mixed>
     */
    public function parse(
        string $body,
        string $contentType,
        string $parserProfile,
        string $finalUrl,
        array $allowedDomains,
    ): array;
}
