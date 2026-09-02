<?php

namespace Tests\Unit\Modules\Announcement;

use App\Modules\Announcement\Domain\AnnouncementItemExtractor;
use PHPUnit\Framework\TestCase;

class AnnouncementItemExtractorTest extends TestCase
{
    private const SOURCE_ID = '0198-1111-7222-8333-000000000001';

    private AnnouncementItemExtractor $extractor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extractor = new AnnouncementItemExtractor;
    }

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

        $result = $this->extractor->extract(
            '<?xml version="1.0"?><rss version="2.0"><channel>'.$items.'</channel></rss>',
            self::SOURCE_ID,
            'rss',
        );

        $this->assertTrue($result['success']);
        $this->assertSame('', $result['error_code']);
        $this->assertCount(7, $result['candidates']);
        $this->assertSame('Item 7', $result['candidates'][6]->title());
        $this->assertSame('https://example.com/items/7', $result['candidates'][6]->canonicalUrl());
    }

    public function test_parses_normal_atom_and_extracts_candidates(): void
    {
        $result = $this->extractor->extract(
            '<?xml version="1.0"?>'.
            '<feed xmlns="http://www.w3.org/2005/Atom">'.
            '<entry><title>Atom One</title><link href="https://example.com/a"/>'.
            '<id>atom-1</id><updated>2024-01-01T00:00:00Z</updated></entry>'.
            '</feed>',
            self::SOURCE_ID,
            'atom',
        );

        $this->assertTrue($result['success']);
        $this->assertSame('', $result['error_code']);
        $this->assertCount(1, $result['candidates']);
        $this->assertSame('Atom One', $result['candidates'][0]->title());
        $this->assertSame('https://example.com/a', $result['candidates'][0]->canonicalUrl());
    }

    public function test_accepts_wordpress_style_content_encoded_cdata_with_doctype_html(): void
    {
        $result = $this->extractor->extract(
            '<?xml version="1.0" encoding="UTF-8"?>'.
            '<rss version="2.0" xmlns:content="http://purl.org/rss/1.0/modules/content/">'.
            '<channel><title>StudyMentor</title>'.
            '<item><title>Post</title><link>https://studymentor.gr/post/</link>'.
            '<guid>https://studymentor.gr/post/</guid>'.
            '<content:encoded><![CDATA['."\n".
            '<!DOCTYPE html>'."\n".
            '<html lang="el"><body><p>Content</p></body></html>'."\n".
            ']]></content:encoded></item></channel></rss>',
            self::SOURCE_ID,
            'rss',
        );

        $this->assertTrue($result['success']);
        $this->assertSame('', $result['error_code']);
        $this->assertNotSame('doctype_not_allowed', $result['error_code']);
        $this->assertCount(1, $result['candidates']);
        $this->assertSame('Post', $result['candidates'][0]->title());
        $this->assertSame('https://studymentor.gr/post/', $result['candidates'][0]->canonicalUrl());
        $payload = $result['candidates'][0]->rawPayload();
        $this->assertArrayHasKey('content', $payload);
        $this->assertStringContainsString('<p>Content</p>', $payload['content']);
        $this->assertArrayNotHasKey('description', $payload);
    }

    public function test_retains_rss_description_in_raw_payload(): void
    {
        $result = $this->extractor->extract(
            '<?xml version="1.0"?><rss version="2.0"><channel>'.
            '<item><title>RSS Item</title><link>https://example.com/rss</link>'.
            '<description>Short RSS description body.</description></item></channel></rss>',
            self::SOURCE_ID,
            'rss',
        );

        $this->assertTrue($result['success']);
        $payload = $result['candidates'][0]->rawPayload();
        $this->assertSame('Short RSS description body.', $payload['description']);
        $this->assertArrayNotHasKey('content', $payload);
    }

    public function test_retains_rss_description_and_content_encoded_independently(): void
    {
        $result = $this->extractor->extract(
            '<?xml version="1.0"?>'.
            '<rss version="2.0" xmlns:content="http://purl.org/rss/1.0/modules/content/">'.
            '<channel><item><title>Both Fields</title><link>https://example.com/both</link>'.
            '<description>Excerpt text</description>'.
            '<content:encoded><![CDATA[<p>Full article body</p>]]></content:encoded>'.
            '</item></channel></rss>',
            self::SOURCE_ID,
            'rss',
        );

        $this->assertTrue($result['success']);
        $payload = $result['candidates'][0]->rawPayload();
        $this->assertSame('Excerpt text', $payload['description']);
        $this->assertSame('<p>Full article body</p>', $payload['content']);
    }

    public function test_retains_atom_summary_in_raw_payload(): void
    {
        $result = $this->extractor->extract(
            '<?xml version="1.0"?>'.
            '<feed xmlns="http://www.w3.org/2005/Atom">'.
            '<entry><title>Atom Summary</title><link href="https://example.com/atom-summary"/>'.
            '<id>atom-summary</id><updated>2024-01-01T00:00:00Z</updated>'.
            '<summary>Atom summary text.</summary></entry></feed>',
            self::SOURCE_ID,
            'atom',
        );

        $this->assertTrue($result['success']);
        $payload = $result['candidates'][0]->rawPayload();
        $this->assertSame('Atom summary text.', $payload['summary']);
        $this->assertArrayNotHasKey('content', $payload);
    }

    public function test_retains_atom_content_in_raw_payload(): void
    {
        $result = $this->extractor->extract(
            '<?xml version="1.0"?>'.
            '<feed xmlns="http://www.w3.org/2005/Atom">'.
            '<entry><title>Atom Content</title><link href="https://example.com/atom-content"/>'.
            '<id>atom-content</id><updated>2024-01-01T00:00:00Z</updated>'.
            '<content type="html">&lt;p&gt;Atom full body&lt;/p&gt;</content></entry></feed>',
            self::SOURCE_ID,
            'atom',
        );

        $this->assertTrue($result['success']);
        $payload = $result['candidates'][0]->rawPayload();
        $this->assertSame('<p>Atom full body</p>', $payload['content']);
    }

    public function test_body_field_size_bound_is_utf8_safe(): void
    {
        $greekChar = 'Ω';
        $overLimitField = str_repeat($greekChar, 70000);
        $result = $this->extractor->extract(
            '<?xml version="1.0"?><rss version="2.0"><channel>'.
            '<item><title>Bounded</title><link>https://example.com/bound</link>'.
            '<description>'.$overLimitField.'</description></item></channel></rss>',
            self::SOURCE_ID,
            'rss',
        );

        $this->assertTrue($result['success']);
        $stored = $result['candidates'][0]->rawPayload()['description'];
        $this->assertSame(65536, mb_strlen($stored, 'UTF-8'));
        $this->assertSame(str_repeat($greekChar, 65536), $stored);
    }

    public function test_accepts_literal_entity_declaration_inside_cdata(): void
    {
        $result = $this->extractor->extract(
            '<?xml version="1.0"?><rss version="2.0"><channel>'.
            '<item><title>Entity text</title><link>https://example.com/entity</link>'.
            '<description><![CDATA[See <!ENTITY example "text"> in docs]]></description>'.
            '</item></channel></rss>',
            self::SOURCE_ID,
            'rss',
        );

        $this->assertTrue($result['success']);
        $this->assertSame('', $result['error_code']);
        $this->assertNotSame('entity_not_allowed', $result['error_code']);
        $this->assertCount(1, $result['candidates']);
        $this->assertSame('Entity text', $result['candidates'][0]->title());
        $this->assertSame(
            'See <!ENTITY example "text"> in docs',
            $result['candidates'][0]->rawPayload()['description'],
        );
    }

    public function test_rejects_external_system_doctype_before_rss(): void
    {
        $result = $this->extractor->extract(
            '<!DOCTYPE rss SYSTEM "file:///etc/passwd"><rss version="2.0"><channel/></rss>',
            self::SOURCE_ID,
            'rss',
        );

        $this->assertFalse($result['success']);
        $this->assertSame('doctype_not_allowed', $result['error_code']);
        $this->assertSame([], $result['candidates']);
    }

    public function test_rejects_public_doctype_before_rss(): void
    {
        $result = $this->extractor->extract(
            '<!DOCTYPE rss PUBLIC "-//Example//DTD RSS//EN" "http://example.com/rss.dtd">'.
            '<rss version="2.0"><channel/></rss>',
            self::SOURCE_ID,
            'rss',
        );

        $this->assertFalse($result['success']);
        $this->assertSame('doctype_not_allowed', $result['error_code']);
    }

    public function test_rejects_internal_subset_dtd_before_rss(): void
    {
        $result = $this->extractor->extract(
            '<!DOCTYPE rss [<!ENTITY xxe SYSTEM "file:///etc/passwd">]>'.
            '<rss version="2.0"><channel><title>&xxe;</title></channel></rss>',
            self::SOURCE_ID,
            'rss',
        );

        $this->assertFalse($result['success']);
        $this->assertSame('doctype_not_allowed', $result['error_code']);
        $this->assertSame([], $result['candidates']);
    }

    public function test_rejects_entity_declaration_in_prolog_without_doctype(): void
    {
        $result = $this->extractor->extract(
            '<!ENTITY foo SYSTEM "file:///etc/passwd">'.
            '<rss version="2.0"><channel/></rss>',
            self::SOURCE_ID,
            'rss',
        );

        $this->assertFalse($result['success']);
        $this->assertSame('entity_not_allowed', $result['error_code']);
    }

    public function test_rejects_malformed_dtd_prolog_before_rss(): void
    {
        $result = $this->extractor->extract(
            '<!DOCTYPE rss [<!ENTITY xxe SYSTEM "file:///etc/passwd"'.
            '<rss version="2.0"><channel><item><title>bypass</title></item></channel></rss>',
            self::SOURCE_ID,
            'rss',
        );

        $this->assertFalse($result['success']);
        $this->assertSame('doctype_not_allowed', $result['error_code']);
    }

    public function test_accepts_utf8_bom_with_xml_declaration_and_rss(): void
    {
        $result = $this->extractor->extract(
            "\xEF\xBB\xBF".'<?xml version="1.0"?>'.
            '<rss version="2.0"><channel>'.
            '<item><title>BOM Item</title><link>https://example.com/bom</link></item>'.
            '</channel></rss>',
            self::SOURCE_ID,
            'rss',
        );

        $this->assertTrue($result['success']);
        $this->assertSame('', $result['error_code']);
        $this->assertCount(1, $result['candidates']);
        $this->assertSame('BOM Item', $result['candidates'][0]->title());
    }

    public function test_rejects_oversized_and_nul_content(): void
    {
        $oversized = $this->extractor->extract(str_repeat('x', 2097153), self::SOURCE_ID, 'rss');
        $nulByte = $this->extractor->extract("<rss>\0</rss>", self::SOURCE_ID, 'rss');

        $this->assertFalse($oversized['success']);
        $this->assertSame('body_too_large', $oversized['error_code']);
        $this->assertSame('invalid_content', $nulByte['error_code']);
    }

    public function test_html_source_type_behavior_remains_unchanged(): void
    {
        $result = $this->extractor->extract(
            '<!DOCTYPE html><html><body><a href="https://example.com/html-item">HTML Item</a></body></html>',
            self::SOURCE_ID,
            'html',
        );

        $this->assertTrue($result['success']);
        $this->assertSame('', $result['error_code']);
        $this->assertCount(1, $result['candidates']);
        $this->assertSame('HTML Item', $result['candidates'][0]->title());
        $this->assertSame('https://example.com/html-item', $result['candidates'][0]->canonicalUrl());
    }

    public function test_asep_announcements_profile_behavior_remains_unchanged(): void
    {
        $result = $this->extractor->extract(
            '<html><body><a href="https://example.com/asep">ASEP Item</a></body></html>',
            self::SOURCE_ID,
            'rss',
            'asep_announcements_v1',
        );

        $this->assertTrue($result['success']);
        $this->assertSame('', $result['error_code']);
        $this->assertCount(1, $result['candidates']);
        $this->assertSame('ASEP Item', $result['candidates'][0]->title());
        $this->assertSame('https://example.com/asep', $result['candidates'][0]->canonicalUrl());
    }

    public function test_asep_diavgeia_profile_filters_mixed_rss_to_relevant_candidates_only(): void
    {
        $result = $this->extractor->extract(
            $this->mixedDiavgeiaRssFeed(),
            self::SOURCE_ID,
            'rss',
            'asep_diavgeia_v1',
        );

        $this->assertTrue($result['success']);
        $this->assertSame('', $result['error_code']);
        $this->assertCount(2, $result['candidates']);

        $titles = array_map(
            static fn ($candidate) => $candidate->title(),
            $result['candidates'],
        );

        $this->assertSame(
            ['Προκήρυξη 3Κ/2026', 'Προσωρινά αποτελέσματα 6Κ/2026'],
            $titles,
        );
    }

    public function test_rss_v1_profile_keeps_all_valid_items_from_same_mixed_feed(): void
    {
        $result = $this->extractor->extract(
            $this->mixedDiavgeiaRssFeed(),
            self::SOURCE_ID,
            'rss',
            'rss_v1',
        );

        $this->assertTrue($result['success']);
        $this->assertSame('', $result['error_code']);
        $this->assertCount(4, $result['candidates']);
    }

    public function test_asep_diavgeia_profile_returns_success_with_zero_candidates_when_all_irrelevant(): void
    {
        $result = $this->extractor->extract(
            '<?xml version="1.0"?><rss version="2.0"><channel>'.
            '<item><title>ΕΝΤΑΛΜΑ ΠΛΗΡΩΜΗΣ</title><link>https://diavgeia.gov.gr/decision/1</link></item>'.
            '<item><title>Τροποποίηση οργανογράμματος</title><link>https://diavgeia.gov.gr/decision/2</link></item>'.
            '</channel></rss>',
            self::SOURCE_ID,
            'rss',
            'asep_diavgeia_v1',
        );

        $this->assertTrue($result['success']);
        $this->assertSame('', $result['error_code']);
        $this->assertSame([], $result['candidates']);
    }

    public function test_extracts_joomla_rss_with_prolog_comment(): void
    {
        $result = $this->extractor->extract(
            '<?xml version="1.0" encoding="utf-8"?>'."\n".
            '<!-- generator="Joomla! - Open Source Content Management" -->'."\n".
            '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">'."\n".
            '    <channel>'."\n".
            '<item><title>MinEdu ASEP</title><link>https://www.minedu.gov.gr/item/1</link></item>'.
            '</channel></rss>',
            self::SOURCE_ID,
            'rss',
        );

        $this->assertTrue($result['success']);
        $this->assertSame('', $result['error_code']);
        $this->assertCount(1, $result['candidates']);
        $this->assertSame('MinEdu ASEP', $result['candidates'][0]->title());
    }

    public function test_extracts_joomla_15_rss_with_prolog_comment(): void
    {
        $result = $this->extractor->extract(
            '<?xml version="1.0" encoding="utf-8"?>'."\n".
            '<!-- generator="Joomla! 1.5 - Open Source Content Management" -->'."\n".
            '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">'."\n".
            '    <channel>'."\n".
            '<item><title>DAA Item</title><link>https://www.daa.gov.gr/item/1</link></item>'.
            '</channel></rss>',
            self::SOURCE_ID,
            'rss',
        );

        $this->assertTrue($result['success']);
        $this->assertSame('', $result['error_code']);
        $this->assertCount(1, $result['candidates']);
        $this->assertSame('DAA Item', $result['candidates'][0]->title());
    }

    public function test_extracts_bom_declaration_comment_rss(): void
    {
        $result = $this->extractor->extract(
            "\xEF\xBB\xBF".'<?xml version="1.0"?>'."\n".
            '<!-- bom comment -->'."\n".
            '<rss version="2.0"><channel>'.
            '<item><title>BOM Comment</title><link>https://example.com/bom-comment</link></item>'.
            '</channel></rss>',
            self::SOURCE_ID,
            'rss',
        );

        $this->assertTrue($result['success']);
        $this->assertSame('', $result['error_code']);
        $this->assertCount(1, $result['candidates']);
        $this->assertSame('BOM Comment', $result['candidates'][0]->title());
    }

    public function test_extracts_atom_with_prolog_comment(): void
    {
        $result = $this->extractor->extract(
            '<?xml version="1.0"?>'."\n".
            '<!-- atom comment -->'."\n".
            '<feed xmlns="http://www.w3.org/2005/Atom">'.
            '<entry><title>Atom Comment</title><link href="https://example.com/atom-comment"/>'.
            '<id>atom-comment</id><updated>2024-01-01T00:00:00Z</updated></entry></feed>',
            self::SOURCE_ID,
            'atom',
        );

        $this->assertTrue($result['success']);
        $this->assertSame('', $result['error_code']);
        $this->assertCount(1, $result['candidates']);
        $this->assertSame('Atom Comment', $result['candidates'][0]->title());
    }

    public function test_rejects_unclosed_prolog_comment(): void
    {
        $result = $this->extractor->extract(
            '<?xml version="1.0"?>'."\n".
            '<!-- unclosed'."\n".
            '<rss version="2.0"><channel>'.
            '<item><title>Broken</title><link>https://example.com/broken</link></item>'.
            '</channel></rss>',
            self::SOURCE_ID,
            'rss',
        );

        $this->assertFalse($result['success']);
        $this->assertSame('unrecognized_content', $result['error_code']);
    }

    private function mixedDiavgeiaRssFeed(): string
    {
        return '<?xml version="1.0"?><rss version="2.0"><channel>'.
            '<item><title>Προκήρυξη 3Κ/2026</title><link>https://diavgeia.gov.gr/decision/101</link></item>'.
            '<item><title>ΕΝΤΑΛΜΑ ΠΛΗΡΩΜΗΣ</title><link>https://diavgeia.gov.gr/decision/102</link></item>'.
            '<item><title>Προσωρινά αποτελέσματα 6Κ/2026</title><link>https://diavgeia.gov.gr/decision/103</link></item>'.
            '<item><title>Τροποποίηση οργανογράμματος</title><link>https://diavgeia.gov.gr/decision/104</link></item>'.
            '</channel></rss>';
    }
}
