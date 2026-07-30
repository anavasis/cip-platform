<?php

namespace StudyMentor\ContentEngine\Registry;

use StudyMentor\ContentEngine\Contracts\ParserHandlerInterface;

defined('ABSPATH') || exit;

final class ParserRegistry
{
    /** @var array<int, ParserHandlerInterface> */
    private $parsers = array();

    /**
     * @return void
     */
    public function register(ParserHandlerInterface $parser)
    {
        $this->parsers[] = $parser;
    }

    /**
     * @param string $sourceType
     * @param string $parserProfile
     * @return bool
     */
    public function supports($sourceType, $parserProfile)
    {
        return $this->resolve($sourceType, $parserProfile) !== null;
    }

    /**
     * @param string $sourceType
     * @param string $parserProfile
     * @return ParserHandlerInterface|null
     */
    public function resolve($sourceType, $parserProfile)
    {
        foreach ($this->parsers as $parser) {
            if ($parser->supports($sourceType, $parserProfile)) {
                return $parser;
            }
        }

        return null;
    }

    /**
     * @return array<int, ParserHandlerInterface>
     */
    public function all()
    {
        return $this->parsers;
    }
}
