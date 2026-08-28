<?php

namespace App\Modules\Delivery\Application;

use App\Modules\Announcement\Infrastructure\Persistence\Models\Announcement;
use App\Modules\Editorial\Domain\Article\ArticlePreview;
use App\Modules\Intelligence\Domain\ContentIntelligencePlan;
use App\Modules\Intelligence\Infrastructure\Persistence\Models\ContentEntityModel;

/**
 * Assembles a versioned delivery package from existing persisted data (no DB writes).
 */
final class PublishPackageBuilder
{
    public const SCHEMA_VERSION = 1;

    /**
     * @return array<string, mixed>
     */
    public function build(
        Announcement $announcement,
        ContentIntelligencePlan $plan,
        ArticlePreview $preview,
        ?ContentEntityModel $entity = null,
    ): array {
        if (! $plan->isResolved()) {
            throw new \InvalidArgumentException('plan_not_resolved');
        }

        if ($plan->action() === ContentIntelligencePlan::ACTION_NO_PUBLISH) {
            throw new \InvalidArgumentException('plan_no_publish');
        }

        $entityId = trim((string) ($plan->entityId() ?? ''));
        if ($entityId === '') {
            throw new \InvalidArgumentException('entity_id_missing');
        }

        $seo = $plan->seoPlan();
        $slug = is_array($seo) && isset($seo['slug']) ? trim((string) $seo['slug']) : '';

        return [
            'schema_version' => self::SCHEMA_VERSION,
            'announcement' => [
                'id' => (string) $announcement->id,
                'title' => (string) $announcement->raw_title,
                'canonical_url' => (string) ($announcement->canonical_url ?? ''),
                'revision_no' => (int) $announcement->revision_no,
                'content_hash' => (string) $announcement->content_hash,
            ],
            'entity' => [
                'entity_id' => $entityId,
                'label' => $plan->entityLabel(),
                'content_role' => $plan->contentRole(),
                'content_entity_id' => $entity !== null ? (string) $entity->id : null,
            ],
            'content_intelligence' => [
                'status' => $plan->status(),
                'action' => $plan->action(),
                'canonical_target_url' => $plan->canonicalTargetUrl(),
                'slug' => $slug !== '' ? $slug : null,
                'parent_hub' => $plan->parentHubEntityId() !== null && $plan->parentHubEntityId() !== '' ? [
                    'entity_id' => $plan->parentHubEntityId(),
                    'label' => $plan->parentHubLabel(),
                    'url' => $plan->parentHubUrl(),
                ] : null,
                'hub_impact' => $plan->hubImpact(),
            ],
            'article' => [
                'preview_id' => $preview->previewId(),
                'title' => $preview->title(),
                'body' => $preview->body(),
                'generated_at' => $preview->createdAtUtc(),
            ],
            'delivery' => [
                'mode' => $plan->action(),
                'wordpress_auto_write_allowed' => $plan->action() === ContentIntelligencePlan::ACTION_CREATE_NEW,
                'wordpress_live_update_allowed' => false,
            ],
        ];
    }
}
