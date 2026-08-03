<?php

namespace Tests\Unit\Modules\Announcement;

use App\Modules\Announcement\Domain\AnnouncementItemExtractor;
use PHPUnit\Framework\TestCase;

class AnnouncementItemExtractorTest extends TestCase
{
    public function test_extracts_the_full_rss_item_list_beyond_preview_limit(): void
    {
        $items = '';

        for ($index = 1; $index <= 7; $index++) {
            $items .= sprintf(
                '<item><title>Item %1$d</title><link>https://example.com/items/%1$d</link>'.
                '<guid>item-%1$d</guid><pubDate>Mon, 01 Jan 2024 00:00:0%1$d +0000</pubDate></item>',
                $index,
            );
        }

        $result = (new AnnouncementItemExtractor)->extract(
            '<?xml version="1.0"?><rss version="2.0"><channel>'.$items.'</channel></rss>',
            '0198-1111-7222-8333-000000000001',
            'rss',
        );

        $this->assertTrue($result['success']);
        $this->assertSame('', $result['error_code']);
        $this->assertCount(7, $result['candidates']);
        $this->assertSame('Item 7', $result['candidates'][6]->title());
        $this->assertSame('https://example.com/items/7', $result['candidates'][6]->canonicalUrl());
    }

    public function test_rejects_oversized_and_dangerous_content(): void
    {
        $extractor = new AnnouncementItemExtractor;
        $sourceId = '0198-1111-7222-8333-000000000001';

        $oversized = $extractor->extract(str_repeat('x', 2097153), $sourceId, 'rss');
        $nulByte = $extractor->extract("<rss>\0</rss>", $sourceId, 'rss');
        $doctype = $extractor->extract(
            '<!DOCTYPE rss [<!ENTITY xxe SYSTEM "file:///etc/passwd">]><rss><channel/></rss>',
            $sourceId,
            'rss',
        );

        $this->assertFalse($oversized['success']);
        $this->assertSame('body_too_large', $oversized['error_code']);
        $this->assertSame('invalid_content', $nulByte['error_code']);
        $this->assertSame('unrecognized_content', $doctype['error_code']);
        $this->assertSame([], $doctype['candidates']);
    }
}
