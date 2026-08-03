<?php

namespace Tests\Feature\Modules\Editorial\Service;

use App\Application\Services\FeatureFlagService;
use App\Domain\Shared\Enums\FeatureFlagScope;
use App\Infrastructure\Persistence\Models\Project;
use App\Modules\Acquisition\Infrastructure\Persistence\Models\Source;
use App\Modules\Announcement\Infrastructure\Persistence\Models\Announcement;
use App\Modules\Editorial\Application\CapabilityGate;
use App\Modules\Editorial\Application\GenerateArticlePreviewService;
use App\Modules\Editorial\Infrastructure\Persistence\Models\ArticlePreviewModel;
use App\Modules\Editorial\Infrastructure\Persistence\Models\GenerationRequestModel;
use Illuminate\Support\Str;
use Tests\TestCase;

class GenerateArticlePreviewServiceTest extends TestCase
{
    public function test_generate_persists_preview_when_capabilities_enabled(): void
    {
        ['user' => $owner, 'organization' => $organization] = $this->createUserWithOrg();
        $project = $this->createProject($organization->id, $owner->id, 'Gen Project');
        $source = $this->createSource($organization->id, $project->id, 'gen-source');
        $ann = $this->createAnnouncement($organization->id, $project->id, $source->id, 'Generate Me');
        $this->enableEditorial($organization->id, $project->id);

        $result = app(GenerateArticlePreviewService::class)->generate(
            $organization->id,
            $project->id,
            $ann->id,
            $owner->id,
        );

        $this->assertTrue($result['ok']);
        $this->assertNotEmpty($result['preview_id']);
        $this->assertDatabaseHas('article_previews', [
            'project_id' => $project->id,
            'announcement_id' => $ann->id,
            'preview_id' => $result['preview_id'],
        ]);
        $this->assertSame(1, GenerationRequestModel::query()->where('project_id', $project->id)->count());
    }

    public function test_capability_fail_closed_blocks_generation(): void
    {
        ['user' => $owner, 'organization' => $organization] = $this->createUserWithOrg();
        $project = $this->createProject($organization->id, $owner->id, 'Blocked Project');
        $source = $this->createSource($organization->id, $project->id, 'blocked-source');
        $ann = $this->createAnnouncement($organization->id, $project->id, $source->id, 'Blocked');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('capability_disabled');
        app(GenerateArticlePreviewService::class)->generate(
            $organization->id,
            $project->id,
            $ann->id,
        );
    }

    public function test_idempotent_reuse_and_regenerate_history(): void
    {
        ['user' => $owner, 'organization' => $organization] = $this->createUserWithOrg();
        $project = $this->createProject($organization->id, $owner->id, 'Idem Project');
        $source = $this->createSource($organization->id, $project->id, 'idem-source');
        $ann = $this->createAnnouncement($organization->id, $project->id, $source->id, 'Idem Title');
        $this->enableEditorial($organization->id, $project->id);
        $service = app(GenerateArticlePreviewService::class);

        $first = $service->generate($organization->id, $project->id, $ann->id);
        $second = $service->generate($organization->id, $project->id, $ann->id);
        $this->assertTrue($first['ok']);
        $this->assertTrue($second['ok']);
        $this->assertTrue($second['reused'] ?? false);
        $this->assertSame(1, GenerationRequestModel::query()->where('project_id', $project->id)->count());

        $regen = $service->generate($organization->id, $project->id, $ann->id, regenerate: true);
        $this->assertTrue($regen['ok']);
        $this->assertFalse($regen['reused'] ?? true);
        $this->assertSame(2, GenerationRequestModel::query()->where('project_id', $project->id)->count());
        $this->assertGreaterThanOrEqual(1, ArticlePreviewModel::query()->where('project_id', $project->id)->count());
    }

    private function enableEditorial(string $organizationId, string $projectId): void
    {
        $flags = app(FeatureFlagService::class);
        foreach ([CapabilityGate::EDITORIAL, CapabilityGate::EDITORIAL_GENERATION] as $key) {
            $flags->upsert(
                $key,
                true,
                FeatureFlagScope::Project,
                null,
                $organizationId,
                $projectId,
            );
        }
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
            'acquire_interval_seconds' => 3600,
        ]);
    }

    private function createAnnouncement(string $organizationId, string $projectId, string $sourceId, string $title): Announcement
    {
        return Announcement::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $organizationId,
            'project_id' => $projectId,
            'source_id' => $sourceId,
            'identity_hash' => hash('sha256', $title.'|'.$projectId.uniqid()),
            'identity_basis' => 'canonical_url',
            'canonical_url' => 'https://example.com/'.Str::slug($title).'-'.uniqid(),
            'raw_title' => $title,
            'content_hash' => hash('sha256', $title.uniqid()),
            'raw_payload' => ['title' => $title, 'summary' => $title.' summary'],
            'revision_no' => 1,
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);
    }
}
