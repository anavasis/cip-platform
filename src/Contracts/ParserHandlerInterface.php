<?php

namespace StudyMentor\ContentEngine\Contracts;

defined('ABSPATH') || exit;

interface ParserHandlerInterface
{
    /**
     * @param string $sourceType
     * @param string $parserProfile
     * @return bool
     */
    public function supports($sourceType, $parserProfile);

    /**
     * @param string $body
     * @param string $contentType
     * @param string $parserProfile
     * @param string $finalUrl
     * @param array<int, string> $allowedDomains
     * @return array<string, mixed>
     */
    public function parse($body, $contentType, $parserProfile, $finalUrl, array $allowedDomains);
}
