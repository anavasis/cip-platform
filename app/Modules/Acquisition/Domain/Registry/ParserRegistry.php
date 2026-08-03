<?php

namespace App\Modules\Acquisition\Domain\Registry;

use App\Modules\Acquisition\Domain\Contracts\ParserHandlerInterface;
use App\Modules\Announcement\Domain\Contracts\ParserRegistryInterface;

final class ParserRegistry implements ParserRegistryInterface
{
    /** @var array<int, ParserHandlerInterface> */
    private array $parsers = [];

    public function register(ParserHandlerInterface $parser): void
    {
        $this->parsers[] = $parser;
    }

    public function supports(string $sourceType, string $parserProfile): bool
    {
        return $this->resolve($sourceType, $parserProfile) !== null;
    }

    public function resolve(string $sourceType, string $parserProfile): ?ParserHandlerInterface
    {
        foreach ($this->parsers as $parser) {
            if ($parser->supports($sourceType, $parserProfile)) {
                return $parser;
            }
        }

        return null;
    }

    /** @return array<int, ParserHandlerInterface> */
    public function all(): array
    {
        return $this->parsers;
    }
}
