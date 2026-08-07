<?php

namespace Tests\Unit\Modules\Acquisition;

use App\Modules\Acquisition\Domain\Feed\FeedPreviewParser;
use Tests\TestCase;

class FeedPreviewParserTest extends TestCase
{
    private FeedPreviewParser $parser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->parser = new FeedPreviewParser;
    }

    public function test_parses_normal_rss(): void
    {
        $result = $this->parser->parse(
            '<?xml version="1.0"?><rss version="2.0"><channel>'.
            '<item><title>One</title><link>https://example.com/1</link><pubDate>Mon, 01 Jan 2024 00:00:00 +0000</pubDate></item>'.
            '</channel></rss>',
        );

        $this->assertTrue($result['success']);
        $this->assertSame('', $result['error_code']);
        $this->assertSame('rss', $result['format']);
        $this->assertSame(1, $result['item_count']);
        $this->assertSame('One', $result['preview_items'][0]['title']);
    }

    public function test_parses_normal_atom(): void
    {
        $result = $this->parser->parse(
            '<?xml version="1.0"?>'.
            '<feed xmlns="http://www.w3.org/2005/Atom">'.
            '<entry><title>Atom One</title><link href="https://example.com/a"/><updated>2024-01-01T00:00:00Z</updated></entry>'.
            '</feed>',
        );

        $this->assertTrue($result['success']);
        $this->assertSame('', $result['error_code']);
        $this->assertSame('atom', $result['format']);
        $this->assertSame(1, $result['item_count']);
        $this->assertSame('Atom One', $result['preview_items'][0]['title']);
    }

    public function test_accepts_doctype_html_inside_content_encoded_cdata(): void
    {
        $result = $this->parser->parse(
            '<?xml version="1.0"?><rss version="2.0"><channel>'.
            '<item><title>With HTML</title><link>https://example.com/html</link>'.
            '<content:encoded xmlns:content="http://purl.org/rss/1.0/modules/content/"><![CDATA['.
            '<!DOCTYPE html><html lang="el"><body><p>Hello</p></body></html>'.
            ']]></content:encoded></item></channel></rss>',
        );

        $this->assertTrue($result['success']);
        $this->assertSame('', $result['error_code']);
        $this->assertSame('rss', $result['format']);
        $this->assertNotSame('doctype_not_allowed', $result['error_code']);
    }

    public function test_accepts_literal_entity_declaration_inside_cdata(): void
    {
        $result = $this->parser->parse(
            '<?xml version="1.0"?><rss version="2.0"><channel>'.
            '<item><title>Entity text</title>'.
            '<description><![CDATA[See <!ENTITY foo SYSTEM "file:///etc/passwd"> in docs]]></description>'.
            '</item></channel></rss>',
        );

        $this->assertTrue($result['success']);
        $this->assertSame('', $result['error_code']);
        $this->assertNotSame('entity_not_allowed', $result['error_code']);
    }

    public function test_accepts_wordpress_style_content_encoded_html_fragment_with_doctype(): void
    {
        $result = $this->parser->parse(
            '<?xml version="1.0" encoding="UTF-8"?>'.
            '<rss version="2.0" xmlns:content="http://purl.org/rss/1.0/modules/content/">'.
            '<channel><title>StudyMentor</title>'.
            '<item><title>Post</title><link>https://studymentor.gr/post/</link>'.
            '<content:encoded><![CDATA['."\n".
            '<!DOCTYPE html>'."\n".
            '<html lang="el">'."\n".
            '<head><meta charset="UTF-8"></head>'."\n".
            '<body><div class="entry">Content</div></body>'."\n".
            '</html>'."\n".
            ']]></content:encoded></item></channel></rss>',
        );

        $this->assertTrue($result['success']);
        $this->assertSame('rss', $result['format']);
        $this->assertSame(1, $result['item_count']);
        $this->assertSame('Post', $result['preview_items'][0]['title']);
    }

    public function test_rejects_external_system_doctype_before_rss(): void
    {
        $result = $this->parser->parse(
            '<!DOCTYPE rss SYSTEM "file:///etc/passwd"><rss version="2.0"><channel/></rss>',
        );

        $this->assertFalse($result['success']);
        $this->assertSame('doctype_not_allowed', $result['error_code']);
    }

    public function test_rejects_public_doctype_before_rss(): void
    {
        $result = $this->parser->parse(
            '<!DOCTYPE rss PUBLIC "-//Example//DTD RSS//EN" "http://example.com/rss.dtd">'.
            '<rss version="2.0"><channel/></rss>',
        );

        $this->assertFalse($result['success']);
        $this->assertSame('doctype_not_allowed', $result['error_code']);
    }

    public function test_rejects_internal_dtd_with_entity_declaration(): void
    {
        $result = $this->parser->parse(
            '<!DOCTYPE rss [<!ENTITY xxe SYSTEM "file:///etc/passwd">]>'.
            '<rss version="2.0"><channel><title>&xxe;</title></channel></rss>',
        );

        $this->assertFalse($result['success']);
        $this->assertSame('doctype_not_allowed', $result['error_code']);
    }

    public function test_rejects_parameter_entity_declaration_in_prolog(): void
    {
        $result = $this->parser->parse(
            '<!DOCTYPE rss [<!ENTITY % pe SYSTEM "http://127.0.0.1/x.dtd"> %pe;]>'.
            '<rss version="2.0"><channel/></rss>',
        );

        $this->assertFalse($result['success']);
        $this->assertSame('doctype_not_allowed', $result['error_code']);
    }

    public function test_rejects_xml_decl_then_dtd_before_rss(): void
    {
        $result = $this->parser->parse(
            '<?xml version="1.0"?>'.
            '<!DOCTYPE rss SYSTEM "http://example.com/evil.dtd">'.
            '<rss version="2.0"><channel><item><title>x</title></item></channel></rss>',
        );

        $this->assertFalse($result['success']);
        $this->assertSame('doctype_not_allowed', $result['error_code']);
    }

    public function test_rejects_empty_body(): void
    {
        $result = $this->parser->parse('');

        $this->assertFalse($result['success']);
        $this->assertSame('empty_body', $result['error_code']);
    }

    public function test_rejects_nul_byte(): void
    {
        $result = $this->parser->parse("<rss version=\"2.0\">\0<channel/></rss>");

        $this->assertFalse($result['success']);
        $this->assertSame('invalid_content', $result['error_code']);
    }

    public function test_rejects_unrecognized_feed(): void
    {
        $result = $this->parser->parse('<html><body>not a feed</body></html>');

        $this->assertFalse($result['success']);
        $this->assertSame('unrecognized_feed', $result['error_code']);
    }

    public function test_rejects_oversized_body(): void
    {
        $result = $this->parser->parse(str_repeat('x', 2097153));

        $this->assertFalse($result['success']);
        $this->assertSame('body_too_large', $result['error_code']);
    }

    public function test_source_retains_libxml_nonet(): void
    {
        $source = (string) file_get_contents(base_path(
            'app/Modules/Acquisition/Domain/Feed/FeedPreviewParser.php',
        ));

        $this->assertStringContainsString('LIBXML_NONET', $source);
        $this->assertStringContainsString('rejectPrologDtdDeclarations', $source);
        $this->assertStringNotContainsString("stripos(\$body, '<!DOCTYPE')", $source);
        $this->assertStringNotContainsString("stripos(\$body, '<!ENTITY')", $source);
    }
}
