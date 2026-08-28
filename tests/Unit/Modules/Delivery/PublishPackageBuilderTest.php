<?php

namespace Tests\Unit\Modules\Delivery;

use App\Modules\Announcement\Infrastructure\Persistence\Models\Announcement;
use App\Modules\Delivery\Application\PublishPackageBuilder;
use App\Modules\Editorial\Domain\Article\ArticlePreview;
use App\Modules\Intelligence\Domain\ContentIntelligencePlan;
use App\Modules\Intelligence\Infrastructure\Persistence\Models\ContentEntityModel;
use Illuminate\Support\Str;
use Tests\TestCase;

class PublishPackageBuilderTest extends TestCase
{
    private PublishPackageBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new PublishPackageBuilder;
    }

    public function test_resolved_create_new_package_includes_article_entity_and_revision_fields(): void
    {
        $announcement = $this->announcement();
        $plan = new ContentIntelligencePlan([
            'status' => ContentIntelligencePlan::STATUS_RESOLVED,
            'entity_id' => 'new-satellite-2026',
            'entity_label' => 'New Satellite',
            'content_role' => 'satellite',
            'action' => ContentIntelligencePlan::ACTION_CREATE_NEW,
            'parent_hub_entity_id' => 'hub-2026',
            'parent_hub_label' => 'Hub 2026',
            'parent_hub_url' => 'https://example.test/hub-2026',
            'seo_plan' => ['slug' => 'new-satellite-2026'],
        ]);
        $preview = $this->preview((string) $announcement->id);
        $entity = new ContentEntityModel([
            'id' => (string) Str::uuid(),
            'entity_id' => 'new-satellite-2026',
        ]);

        $package = $this->builder->build($announcement, $plan, $preview, $entity);

        $this->assertSame(1, $package['schema_version']);
        $this->assertSame((string) $announcement->id, $package['announcement']['id']);
        $this->assertSame(1, $package['announcement']['revision_no']);
        $this->assertSame($announcement->content_hash, $package['announcement']['content_hash']);
        $this->assertSame('new-satellite-2026', $package['entity']['entity_id']);
        $this->assertSame((string) $entity->id, $package['entity']['content_entity_id']);
        $this->assertSame(ContentIntelligencePlan::ACTION_CREATE_NEW, $package['content_intelligence']['action']);
        $this->assertSame('new-satellite-2026', $package['content_intelligence']['slug']);
        $this->assertSame('https://example.test/hub-2026', $package['content_intelligence']['parent_hub']['url']);
        $this->assertSame('Article title', $package['article']['title']);
        $this->assertSame('Article body content.', $package['article']['body']);
        $this->assertTrue($package['delivery']['wordpress_auto_write_allowed']);
        $this->assertFalse($package['delivery']['wordpress_live_update_allowed']);
    }

    public function test_resolved_update_existing_package_includes_target_url_and_slug(): void
    {
        $announcement = $this->announcement(revision: 2);
        $plan = new ContentIntelligencePlan([
            'status' => ContentIntelligencePlan::STATUS_RESOLVED,
            'entity_id' => 'existing-satellite',
            'entity_label' => 'Existing Satellite',
            'content_role' => 'satellite',
            'action' => ContentIntelligencePlan::ACTION_UPDATE_EXISTING,
            'canonical_target_url' => 'https://example.test/existing-satellite',
            'seo_plan' => ['slug' => 'existing-satellite'],
        ]);
        $preview = $this->preview((string) $announcement->id);

        $package = $this->builder->build($announcement, $plan, $preview, null);

        $this->assertSame(ContentIntelligencePlan::ACTION_UPDATE_EXISTING, $package['content_intelligence']['action']);
        $this->assertSame('https://example.test/existing-satellite', $package['content_intelligence']['canonical_target_url']);
        $this->assertSame('existing-satellite', $package['content_intelligence']['slug']);
        $this->assertFalse($package['delivery']['wordpress_auto_write_allowed']);
    }

    public function test_unresolved_plan_fails_closed(): void
    {
        $announcement = $this->announcement();
        $plan = new ContentIntelligencePlan([
            'status' => ContentIntelligencePlan::STATUS_UNRESOLVED,
            'action' => ContentIntelligencePlan::ACTION_NO_PUBLISH,
        ]);
        $preview = $this->preview((string) $announcement->id);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('plan_not_resolved');
        $this->builder->build($announcement, $plan, $preview, null);
    }

    public function test_no_publish_plan_fails_closed(): void
    {
        $announcement = $this->announcement();
        $plan = new ContentIntelligencePlan([
            'status' => ContentIntelligencePlan::STATUS_RESOLVED,
            'entity_id' => 'blocked-entity',
            'action' => ContentIntelligencePlan::ACTION_NO_PUBLISH,
        ]);
        $preview = $this->preview((string) $announcement->id);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('plan_no_publish');
        $this->builder->build($announcement, $plan, $preview, null);
    }

    private function announcement(int $revision = 1): Announcement
    {
        return new Announcement([
            'id' => (string) Str::uuid(),
            'raw_title' => 'Source announcement title',
            'canonical_url' => 'https://example.test/source',
            'revision_no' => $revision,
            'content_hash' => hash('sha256', 'content-'.$revision),
        ]);
    }

    private function preview(string $announcementId): ArticlePreview
    {
        return new ArticlePreview([
            'preview_id' => 'preview-1',
            'announcement_id' => $announcementId,
            'title' => 'Article title',
            'body' => 'Article body content.',
            'created_at_utc' => '2026-08-28T12:00:00Z',
        ]);
    }
}
