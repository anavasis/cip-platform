<?php

namespace App\Modules\Acquisition\Domain\Registry;

use App\Modules\Acquisition\Domain\Contracts\ParserHandlerInterface;
use App\Modules\Acquisition\Domain\Feed\FeedPreviewParser;

final readonly class FeedPreviewParserHandler implements ParserHandlerInterface
{
    public function __construct(private FeedPreviewParser $parser) {}

    public function supports(string $sourceType, string $parserProfile): bool
    {
        return in_array(strtolower(trim($sourceType)), ['rss', 'atom'], true);
    }

    public function parse(
        string $body,
        string $contentType,
        string $parserProfile,
        string $finalUrl,
        array $allowedDomains,
    ): array {
        return $this->parser->parse($body);
    }
}
