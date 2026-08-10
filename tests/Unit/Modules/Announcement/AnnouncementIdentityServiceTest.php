<?php

namespace Tests\Unit\Modules\Announcement;

use App\Modules\Acquisition\Domain\Fingerprint\FingerprintService;
use App\Modules\Announcement\Domain\AnnouncementCandidate;
use App\Modules\Announcement\Domain\AnnouncementIdentityService;
use PHPUnit\Framework\TestCase;

class AnnouncementIdentityServiceTest extends TestCase
{
    private AnnouncementIdentityService $identity;

    protected function setUp(): void
    {
        parent::setUp();

        $this->identity = new AnnouncementIdentityService;
    }

    public function test_identity_hash_is_sha256_of_normalized_url(): void
    {
        $url = ' HTTPS://Example.COM/news/item ';
        $normalized = $this->identity->normalizeUrl($url);

        $this->assertSame('https://example.com/news/item', $normalized);
        $this->assertSame(hash('sha256', $normalized), $this->identity->identityHash($url));
        $this->assertSame(AnnouncementIdentityService::IDENTITY_BASIS_CANONICAL_URL, $this->identity->identityBasis());
    }

    public function test_normalize_url_trims_and_lowercases_preserving_foundation_algorithm(): void
    {
        // Foundation algorithm: trim + strtolower only (no path/port/fragment rewriting).
        $this->assertSame(
            'http://example.com:80//news//item/?b=2&a=1#fragment',
            $this->identity->normalizeUrl('HTTP://EXAMPLE.COM:80//News//Item/?b=2&a=1#fragment'),
        );
        $this->assertSame('', $this->identity->normalizeUrl('   '));
    }

    public function test_content_hash_changes_when_title_changes(): void
    {
        $original = $this->candidate('Original title');
        $revised = $this->candidate('Revised title');

        $this->assertNotSame(
            $this->identity->contentHash($original),
            $this->identity->contentHash($revised),
        );
    }

    public function test_item_identity_is_independent_from_feed_fingerprints(): void
    {
        $url = 'https://example.com/news/item';
        $body = '<rss><channel><title>Same body</title></channel></rss>';
        $itemIdentity = $this->identity->identityHash($url);
        $feedFingerprints = (new FingerprintService)->fingerprint($body, $url, 'source-a');

        $this->assertSame(hash('sha256', $url), $itemIdentity);
        $this->assertSame(hash('sha256', 'source-a|'.$url), $feedFingerprints['identity_hash']);
        $this->assertNotSame($itemIdentity, $feedFingerprints['identity_hash']);
        $this->assertNotSame($itemIdentity, $feedFingerprints['body_hash']);
        $this->assertNotSame($itemIdentity, $feedFingerprints['content_hash']);
    }

    public function test_content_hash_still_changes_when_metadata_changes(): void
    {
        $original = $this->candidate('Same title');
        $revised = $this->candidate('Same title', publishedAtUtc: '2026-08-04 08:00:00');

        $this->assertNotSame(
            $this->identity->contentHash($original),
            $this->identity->contentHash($revised),
        );
    }

    public function test_content_hash_changes_when_body_changes_with_same_metadata(): void
    {
        $original = $this->candidate('Same title', rawPayload: [
            'title' => 'Same title',
            'content' => '<p>Original body</p>',
        ]);
        $revised = $this->candidate('Same title', rawPayload: [
            'title' => 'Same title',
            'content' => '<p>Revised body</p>',
        ]);

        $this->assertNotSame(
            $this->identity->contentHash($original),
            $this->identity->contentHash($revised),
        );
    }

    public function test_content_hash_ignores_insignificant_html_and_whitespace_body_differences(): void
    {
        $first = $this->candidate('Same title', rawPayload: [
            'title' => 'Same title',
            'content' => "<p>Hello   world</p>\r\n",
        ]);
        $second = $this->candidate('Same title', rawPayload: [
            'title' => 'Same title',
            'content' => "Hello world\n",
        ]);

        $this->assertSame(
            $this->identity->contentHash($first),
            $this->identity->contentHash($second),
        );
    }

    public function test_identity_hash_is_independent_of_body_content(): void
    {
        $withoutBody = $this->candidate('Title');
        $withBody = $this->candidate('Title', rawPayload: [
            'title' => 'Title',
            'content' => '<p>Body text</p>',
        ]);

        $this->assertSame(
            $this->identity->identityHash($withoutBody->canonicalUrl()),
            $this->identity->identityHash($withBody->canonicalUrl()),
        );
    }

    public function test_content_hash_body_priority_is_content_over_description_over_summary(): void
    {
        $base = [
            'title' => 'Same title',
            'content' => '<p>Primary content</p>',
            'description' => '<p>Secondary description</p>',
            'summary' => 'Tertiary summary',
        ];
        $contentOnlyChange = $base;
        $contentOnlyChange['description'] = '<p>Changed description</p>';
        $contentOnlyChange['summary'] = 'Changed summary';

        $descriptionOnly = [
            'title' => 'Same title',
            'description' => '<p>Description body</p>',
            'summary' => 'Summary body',
        ];
        $summaryOnly = [
            'title' => 'Same title',
            'summary' => 'Summary body',
        ];

        $withContent = $this->candidate('Same title', rawPayload: $base);
        $withContentVariant = $this->candidate('Same title', rawPayload: $contentOnlyChange);
        $withDescription = $this->candidate('Same title', rawPayload: $descriptionOnly);
        $withDescriptionChanged = $this->candidate('Same title', rawPayload: [
            'title' => 'Same title',
            'description' => '<p>Different description</p>',
            'summary' => 'Summary body',
        ]);
        $withSummary = $this->candidate('Same title', rawPayload: $summaryOnly);
        $withSummaryChanged = $this->candidate('Same title', rawPayload: [
            'title' => 'Same title',
            'summary' => 'Different summary',
        ]);

        $this->assertSame(
            $this->identity->contentHash($withContent),
            $this->identity->contentHash($withContentVariant),
        );
        $this->assertNotSame(
            $this->identity->contentHash($withDescription),
            $this->identity->contentHash($withDescriptionChanged),
        );
        $this->assertNotSame(
            $this->identity->contentHash($withSummary),
            $this->identity->contentHash($withSummaryChanged),
        );
    }

    public function test_content_hash_without_body_is_deterministic_with_empty_source_body(): void
    {
        $first = $this->candidate('Legacy title');
        $second = $this->candidate('Legacy title');

        $this->assertSame(
            $this->identity->contentHash($first),
            $this->identity->contentHash($second),
        );
    }

    /**
     * @param  array<string, mixed>  $rawPayload
     */
    private function candidate(
        string $title,
        array $rawPayload = [],
        string $publishedAtUtc = '2026-08-03 08:00:00',
    ): AnnouncementCandidate {
        if ($rawPayload === []) {
            $rawPayload = ['title' => $title];
        }

        return new AnnouncementCandidate([
            'source_id' => '0198-1111-7222-8333-000000000001',
            'title' => $title,
            'canonical_url' => 'https://example.com/news/item',
            'source_guid' => 'guid-1',
            'published_at_utc' => $publishedAtUtc,
            'raw_payload' => $rawPayload,
        ]);
    }
}
