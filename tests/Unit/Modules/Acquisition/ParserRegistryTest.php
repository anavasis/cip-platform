<?php

namespace Tests\Unit\Modules\Acquisition;

use App\Modules\Acquisition\Domain\Feed\AsepAnnouncementsHtmlParser;
use App\Modules\Acquisition\Domain\Feed\FeedPreviewParser;
use App\Modules\Acquisition\Domain\Registry\AsepHtmlParserHandler;
use App\Modules\Acquisition\Domain\Registry\FeedPreviewParserHandler;
use App\Modules\Acquisition\Domain\Registry\ParserRegistry;
use PHPUnit\Framework\TestCase;

class ParserRegistryTest extends TestCase
{
    public function test_feed_preview_and_asep_handlers_resolve_for_supported_sources(): void
    {
        $feedHandler = new FeedPreviewParserHandler(new FeedPreviewParser);
        $asepHandler = new AsepHtmlParserHandler(new AsepAnnouncementsHtmlParser);
        $registry = new ParserRegistry;
        $registry->register($feedHandler);
        $registry->register($asepHandler);

        $this->assertSame($feedHandler, $registry->resolve('rss', ''));
        $this->assertSame($feedHandler, $registry->resolve('ATOM', 'ignored'));
        $this->assertSame(
            $asepHandler,
            $registry->resolve('html', AsepAnnouncementsHtmlParser::SUPPORTED_PROFILE),
        );
        $this->assertTrue($registry->supports('rss', ''));
        $this->assertFalse($registry->supports('html', 'unknown_profile'));
        $this->assertNull($registry->resolve('json', ''));
        $this->assertCount(2, $registry->all());
    }
}
