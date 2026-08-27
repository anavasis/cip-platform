<?php

namespace App\Modules\Intelligence\Application;

use App\Modules\Announcement\Infrastructure\Persistence\Models\Announcement;
use App\Modules\Intelligence\Domain\ContentIntelligencePlan;
use App\Modules\Intelligence\Infrastructure\Persistence\Models\ContentEntityModel;
use App\Modules\Intelligence\Infrastructure\Persistence\Models\EntityAnnouncementBindingModel;
use App\Modules\Intelligence\Infrastructure\Persistence\Models\RemotePostBindingModel;
use Illuminate\Support\Facades\DB;

/**
 * Materializes durable entity identity and bindings from resolved Content Intelligence plans.
 */
final class EntityBindingService
{
    public function __construct(
        private readonly ContentIntelligencePlanner $planner,
    ) {}

    /**
     * @return array{bound: bool, reason: string, entity_id: string|null, content_entity_id: string|null}
     */
    public function bindAnnouncement(Announcement $announcement): array
    {
        $organizationId = (string) $announcement->organization_id;
        $projectId = (string) $announcement->project_id;

        $plan = $this->planner->planForAnnouncement($organizationId, $projectId, $announcement);

        if ($plan->status() !== ContentIntelligencePlan::STATUS_RESOLVED) {
            return [
                'bound' => false,
                'reason' => 'plan_not_resolved',
                'entity_id' => null,
                'content_entity_id' => null,
            ];
        }

        if ($plan->action() === ContentIntelligencePlan::ACTION_NO_PUBLISH) {
            return [
                'bound' => false,
                'reason' => 'no_publish',
                'entity_id' => null,
                'content_entity_id' => null,
            ];
        }

        $entityId = (string) $plan->entityId();
        if ($entityId === '') {
            return [
                'bound' => false,
                'reason' => 'missing_entity_id',
                'entity_id' => null,
                'content_entity_id' => null,
            ];
        }

        if ($plan->primaryBindingEligible() !== true) {
            return [
                'bound' => false,
                'reason' => 'primary_binding_ineligible',
                'entity_id' => $entityId,
                'content_entity_id' => null,
            ];
        }

        return DB::transaction(function () use (
            $announcement,
            $plan,
            $organizationId,
            $projectId,
            $entityId,
        ): array {
            $now = now();
            $entity = ContentEntityModel::query()
                ->where('organization_id', $organizationId)
                ->where('project_id', $projectId)
                ->where('entity_id', $entityId)
                ->lockForUpdate()
                ->first();

            if ($entity === null) {
                $entity = ContentEntityModel::create([
                    'organization_id' => $organizationId,
                    'project_id' => $projectId,
                    'entity_id' => $entityId,
                    'entity_type' => 'process',
                    'label' => trim((string) ($plan->entityLabel() ?? $entityId)),
                    'content_role' => trim((string) ($plan->contentRole() ?? 'satellite')) ?: 'satellite',
                    'lifecycle_status' => 'verification_required',
                    'verification_status' => 'verification_required',
                    'hub_member' => false,
                    'publish_eligible' => false,
                    'archive_state' => 'active',
                    'source_family' => 'other',
                    'thematic_categories' => [],
                    'last_changed_at' => $now,
                ]);
            } else {
                $updates = [];

                $label = trim((string) ($plan->entityLabel() ?? ''));
                if ($label !== '' && trim((string) $entity->label) === '') {
                    $updates['label'] = $label;
                }

                $contentRole = trim((string) ($plan->contentRole() ?? ''));
                if ($contentRole !== '' && trim((string) $entity->content_role) === '') {
                    $updates['content_role'] = $contentRole;
                }

                if ($updates !== []) {
                    $entity->fill($updates);
                    $entity->save();
                }
            }

            $binding = EntityAnnouncementBindingModel::query()
                ->where('organization_id', $organizationId)
                ->where('project_id', $projectId)
                ->where('content_entity_id', $entity->id)
                ->where('announcement_id', $announcement->id)
                ->first();

            $bindingPayload = [
                'source_revision_at_bind' => (int) $announcement->revision_no,
                'content_hash_at_bind' => (string) $announcement->content_hash,
                'bound_at' => $now,
            ];

            if ($binding === null) {
                EntityAnnouncementBindingModel::create(array_merge([
                    'organization_id' => $organizationId,
                    'project_id' => $projectId,
                    'content_entity_id' => $entity->id,
                    'announcement_id' => $announcement->id,
                    'binding_role' => 'supplemental',
                ], $bindingPayload));
            } elseif (
                (int) $binding->source_revision_at_bind !== (int) $announcement->revision_no
                || (string) $binding->content_hash_at_bind !== (string) $announcement->content_hash
            ) {
                $binding->fill($bindingPayload);
                $binding->save();
            }

            $canonicalUrl = trim((string) ($plan->canonicalTargetUrl() ?? ''));
            if ($canonicalUrl !== '') {
                $this->upsertUnconfirmedRemotePostBinding(
                    $organizationId,
                    $projectId,
                    $entity,
                    $canonicalUrl,
                    $now,
                );
            }

            return [
                'bound' => true,
                'reason' => 'bound',
                'entity_id' => $entityId,
                'content_entity_id' => (string) $entity->id,
            ];
        });
    }

    private function upsertUnconfirmedRemotePostBinding(
        string $organizationId,
        string $projectId,
        ContentEntityModel $entity,
        string $canonicalUrl,
        \DateTimeInterface $now,
    ): void {
        $binding = RemotePostBindingModel::query()
            ->where('organization_id', $organizationId)
            ->where('project_id', $projectId)
            ->where('content_entity_id', $entity->id)
            ->where('remote_system', 'wordpress')
            ->first();

        if ($binding === null) {
            RemotePostBindingModel::create([
                'organization_id' => $organizationId,
                'project_id' => $projectId,
                'content_entity_id' => $entity->id,
                'remote_system' => 'wordpress',
                'remote_post_id' => null,
                'canonical_url' => $canonicalUrl,
                'slug' => null,
                'confirmed_at' => null,
                'confirmed_by' => null,
                'bound_at' => $now,
                'last_synced_at' => null,
            ]);

            return;
        }

        if ($binding->confirmed_at !== null) {
            return;
        }

        if ((string) $binding->canonical_url !== $canonicalUrl) {
            $binding->canonical_url = $canonicalUrl;
            $binding->bound_at = $now;
            $binding->save();
        }
    }
}
