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
        $url = ' HTTPS://Example.COM:443/news/item/?page=1#section ';
        $normalized = $this->identity->normalizeUrl($url);

        $this->assertSame('https://example.com/news/item?page=1', $normalized);
        $this->assertSame(hash('sha256', $normalized), $this->identity->identityHash($url));
        $this->assertSame(AnnouncementIdentityService::IDENTITY_BASIS_CANONICAL_URL, $this->identity->identityBasis());
    }

    public function test_normalize_url_canonicalizes_scheme_host_port_path_and_fragment(): void
    {
        $this->assertSame(
            'http://example.com/News/Item?b=2&a=1',
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

    private function candidate(string $title): AnnouncementCandidate
    {
        return new AnnouncementCandidate([
            'source_id' => '0198-1111-7222-8333-000000000001',
            'title' => $title,
            'canonical_url' => 'https://example.com/news/item',
            'source_guid' => 'guid-1',
            'published_at_utc' => '2026-08-03 08:00:00',
            'raw_payload' => ['title' => $title],
        ]);
    }
}
