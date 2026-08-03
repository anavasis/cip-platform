<?php

namespace Tests\Feature\Modules\Announcement;

use App\Infrastructure\Persistence\Models\Project;
use App\Modules\Acquisition\Infrastructure\Persistence\Models\Source;
use App\Modules\Announcement\Application\EditorialIngestionService;
use App\Modules\Announcement\Domain\AnnouncementCandidate;
use App\Modules\Announcement\Domain\LifecycleOutcome;
use App\Modules\Announcement\Infrastructure\Persistence\Models\Announcement;
use Tests\TestCase;

class AnnouncementLifecycleFeatureTest extends TestCase
{
    public function test_editorial_ingestion_persists_full_lifecycle_and_batch_duplicates(): void
    {
        ['user' => $owner, 'organization' => $organization] = $this->createUserWithOrg();
        $project = $this->createProject($organization->id, $owner->id, 'Project A');
        $source = $this->createSource($organization->id, $project->id, 'source-a');
        $ingestion = app(EditorialIngestionService::class)->forTenant($organization->id, $project->id);
        $original = $this->candidate($source->id, 'Original title');

        $new = $ingestion->ingestCandidates([$original]);
        $this->assertTrue($new->success());
        $this->assertSame(1, $new->newCount());
        $this->assertSame(LifecycleOutcome::NEW_ITEM, $new->decisions()[0]->outcome());
        $this->assertDatabaseHas('announcements', [
            'project_id' => $project->id,
            'source_id' => $source->id,
            'raw_title' => 'Original title',
            'revision_no' => 1,
        ]);

        $unchanged = $ingestion->ingestCandidates([$original]);
        $this->assertSame(1, $unchanged->unchangedCount());
        $this->assertSame(LifecycleOutcome::UNCHANGED, $unchanged->decisions()[0]->outcome());

        $revised = $this->candidate($source->id, 'Revised title');
        $updated = $ingestion->ingestCandidates([$revised]);
        $this->assertSame(1, $updated->updatedCount());
        $this->assertSame(2, $updated->decisions()[0]->revisionNo());
        $this->assertDatabaseHas('announcements', [
            'project_id' => $project->id,
            'source_id' => $source->id,
            'raw_title' => 'Revised title',
            'revision_no' => 2,
        ]);

        $duplicateBatch = $ingestion->ingestCandidates([$revised, $revised]);
        $this->assertSame(1, $duplicateBatch->unchangedCount());
        $this->assertSame(1, $duplicateBatch->duplicateCount());
        $this->assertSame(LifecycleOutcome::DUPLICATE, $duplicateBatch->decisions()[1]->outcome());
        $this->assertDatabaseCount('announcements', 1);
    }

    public function test_same_identity_hash_is_new_in_another_project(): void
    {
        ['user' => $owner, 'organization' => $organization] = $this->createUserWithOrg();
        $projectA = $this->createProject($organization->id, $owner->id, 'Project A');
        $projectB = $this->createProject($organization->id, $owner->id, 'Project B');
        $sourceA = $this->createSource($organization->id, $projectA->id, 'source-a');
        $sourceB = $this->createSource($organization->id, $projectB->id, 'source-b');
        $ingestion = app(EditorialIngestionService::class);

        $first = $ingestion
            ->forTenant($organization->id, $projectA->id)
            ->ingestCandidates([$this->candidate($sourceA->id, 'Shared title')]);
        $second = $ingestion
            ->forTenant($organization->id, $projectB->id)
            ->ingestCandidates([$this->candidate($sourceB->id, 'Shared title')]);

        $this->assertSame(1, $first->newCount());
        $this->assertSame(1, $second->newCount());
        $this->assertSame(
            $first->decisions()[0]->identityHash(),
            $second->decisions()[0]->identityHash(),
        );
        $this->assertSame(2, Announcement::query()
            ->where('identity_hash', $first->decisions()[0]->identityHash())
            ->count());
    }

    private function candidate(string $sourceId, string $title): AnnouncementCandidate
    {
        return new AnnouncementCandidate([
            'source_id' => $sourceId,
            'title' => $title,
            'canonical_url' => 'https://example.com/announcements/shared',
            'source_guid' => 'shared-guid',
            'published_at_utc' => '2026-08-03 08:00:00',
            'raw_payload' => ['title' => $title],
        ]);
    }

    private function createProject(string $organizationId, string $userId, string $name): Project
    {
        return Project::create([
            'organization_id' => $organizationId,
            'name' => $name,
            'slug' => strtolower(str_replace(' ', '-', $name)).'-'.uniqid(),
            'created_by' => $userId,
        ]);
    }

    private function createSource(string $organizationId, string $projectId, string $slug): Source
    {
        $feedUrl = "https://example.com/{$slug}.xml";

        return Source::create([
            'organization_id' => $organizationId,
            'project_id' => $projectId,
            'slug' => $slug,
            'name' => $slug,
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
