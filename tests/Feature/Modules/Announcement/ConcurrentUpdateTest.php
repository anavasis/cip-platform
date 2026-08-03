<?php

namespace Tests\Feature\Modules\Announcement;

use App\Infrastructure\Persistence\Models\Project;
use App\Modules\Acquisition\Infrastructure\Persistence\Models\Source;
use App\Modules\Announcement\Application\EditorialIngestionService;
use App\Modules\Announcement\Domain\AnnouncementCandidate;
use App\Modules\Announcement\Infrastructure\Persistence\Models\Announcement;
use Tests\TestCase;

class ConcurrentUpdateTest extends TestCase
{
    public function test_repeated_updates_observe_latest_hash_and_increment_revision(): void
    {
        ['user' => $owner, 'organization' => $organization] = $this->createUserWithOrg();
        $project = Project::create([
            'organization_id' => $organization->id,
            'name' => 'Concurrent Updates',
            'slug' => 'concurrent-updates',
            'created_by' => $owner->id,
        ]);
        $source = $this->createSource($organization->id, $project->id);
        $ingestion = app(EditorialIngestionService::class)->forTenant($organization->id, $project->id);

        $created = $ingestion->ingestCandidates([$this->candidate($source->id, 'Version 1')]);
        $firstUpdate = $ingestion->ingestCandidates([$this->candidate($source->id, 'Version 2')]);
        $secondUpdate = $ingestion->ingestCandidates([$this->candidate($source->id, 'Version 3')]);

        $this->assertSame(1, $created->decisions()[0]->revisionNo());
        $this->assertSame(2, $firstUpdate->decisions()[0]->revisionNo());
        $this->assertSame(3, $secondUpdate->decisions()[0]->revisionNo());
        $this->assertNotSame(
            $firstUpdate->decisions()[0]->contentHash(),
            $secondUpdate->decisions()[0]->contentHash(),
        );

        $announcement = Announcement::query()->sole();
        $this->assertSame(3, $announcement->revision_no);
        $this->assertSame('Version 3', $announcement->raw_title);
        $this->assertSame($secondUpdate->decisions()[0]->contentHash(), $announcement->content_hash);
    }

    private function candidate(string $sourceId, string $title): AnnouncementCandidate
    {
        return new AnnouncementCandidate([
            'source_id' => $sourceId,
            'title' => $title,
            'canonical_url' => 'https://example.com/versioned-item',
            'source_guid' => 'versioned-item',
            'published_at_utc' => '2026-08-03 08:00:00',
            'raw_payload' => ['title' => $title],
        ]);
    }

    private function createSource(string $organizationId, string $projectId): Source
    {
        $feedUrl = 'https://example.com/concurrent.xml';

        return Source::create([
            'organization_id' => $organizationId,
            'project_id' => $projectId,
            'slug' => 'concurrent-feed',
            'name' => 'Concurrent Feed',
            'source_type' => 'rss',
            'base_url' => 'https://example.com',
            'feed_url' => $feedUrl,
            'feed_url_hash' => hash('sha256', $feedUrl),
            'allowed_domains' => ['example.com'],
            'enabled' => true,
            'manual_only' => false,
        ]);
    }
}
