<?php

namespace StudyMentor\ContentEngine\Registry;

use StudyMentor\ContentEngine\Contracts\ParserHandlerInterface;
use StudyMentor\ContentEngine\Feed\AsepAnnouncementsHtmlParser;

defined('ABSPATH') || exit;

final class AsepHtmlParserHandler implements ParserHandlerInterface
{
    private $parser;

    public function __construct(AsepAnnouncementsHtmlParser $parser)
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
        $profile = trim((string) $parserProfile);

        return $type === 'html' && $profile === AsepAnnouncementsHtmlParser::SUPPORTED_PROFILE;
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
        return $this->parser->parse(
            (string) $body,
            (string) $contentType,
            (string) $parserProfile,
            (string) $finalUrl,
            $allowedDomains
        );
    }
}
