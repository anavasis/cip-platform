<?php

namespace StudyMentor\ContentEngine\Registry;

use StudyMentor\ContentEngine\Contracts\ParserHandlerInterface;
use StudyMentor\ContentEngine\Feed\FeedPreviewParser;

defined('ABSPATH') || exit;

final class FeedPreviewParserHandler implements ParserHandlerInterface
{
    private $parser;

    public function __construct(FeedPreviewParser $parser)
    {
        $this->parser = $parser;
    }

    /**
     * @param string $sourceType
     * @param string $parserProfile
     * @return bool
     */
    public function supports($sourceType, $parserProfile)
    {
        $type = strtolower(trim((string) $sourceType));

        return $type === 'rss' || $type === 'atom';
    }

    /**
     * @param string $body
     * @param string $contentType
     * @param string $parserProfile
     * @param string $finalUrl
     * @param array<int, string> $allowedDomains
     * @return array<string, mixed>
     */
    public function parse($body, $contentType, $parserProfile, $finalUrl, array $allowedDomains)
    {
        return $this->parser->parse($body);
    }
}
