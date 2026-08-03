<?php

namespace App\Modules\Acquisition\Domain\Registry;

use App\Modules\Acquisition\Domain\Contracts\ParserHandlerInterface;
use App\Modules\Acquisition\Domain\Feed\AsepAnnouncementsHtmlParser;

final readonly class AsepHtmlParserHandler implements ParserHandlerInterface
{
    public function __construct(private AsepAnnouncementsHtmlParser $parser) {}

    public function supports(string $sourceType, string $parserProfile): bool
    {
        return strtolower(trim($sourceType)) === 'html'
            && trim($parserProfile) === AsepAnnouncementsHtmlParser::SUPPORTED_PROFILE;
    }

    public function parse(
        string $body,
        string $contentType,
        string $parserProfile,
        string $finalUrl,
        array $allowedDomains,
    ): array {
        return $this->parser->parse(
            $body,
            $contentType,
            $parserProfile,
            $finalUrl,
            $allowedDomains,
        );
    }
}
