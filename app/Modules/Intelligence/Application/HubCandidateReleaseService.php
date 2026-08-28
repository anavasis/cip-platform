<?php

namespace App\Modules\Intelligence\Application;

use App\Modules\Announcement\Infrastructure\Persistence\Models\Announcement;
use App\Modules\Intelligence\Infrastructure\Persistence\Models\ContentEntityModel;
use App\Modules\Intelligence\Infrastructure\Persistence\Models\EntityAnnouncementBindingModel;
use App\Modules\Intelligence\Infrastructure\Persistence\Models\RemotePostBindingModel;
use Illuminate\Support\Facades\DB;

/**
 * Explicit operator Hub release using existing PR12 columns only.
 */
final class HubCandidateReleaseService
{
    private const ALLOWED_LIFECYCLE = [
        'open',
        'in_progress',
        'results',
        'objections',
        'final_results',
        'verification_required',
        'completed',
        'archived',
    ];

    /**
     * @return array{ok: bool, reason: string|null, entity_id: string|null}
     */
    public function release(
        ContentEntityModel $entity,
        Announcement $announcement,
        string $operatorUserId,
        string $lifecycleStatus,
        string $canonicalUrl,
        bool $confirmed,
    ): array {
        if (! $confirmed) {
            return ['ok' => false, 'reason' => 'hub_release_not_confirmed', 'entity_id' => null];
        }

        $lifecycleStatus = trim($lifecycleStatus);
        if (! in_array($lifecycleStatus, self::ALLOWED_LIFECYCLE, true)) {
            return ['ok' => false, 'reason' => 'invalid_lifecycle_status', 'entity_id' => null];
        }

        $canonicalUrl = trim($canonicalUrl);
        if ($canonicalUrl === '' || filter_var($canonicalUrl, FILTER_VALIDATE_URL) === false) {
            return ['ok' => false, 'reason' => 'invalid_canonical_url', 'entity_id' => null];
        }

        if ((string) $entity->organization_id !== (string) $announcement->organization_id
            || (string) $entity->project_id !== (string) $announcement->project_id) {
            return ['ok' => false, 'reason' => 'tenant_mismatch', 'entity_id' => null];
        }

        $bindingExists = EntityAnnouncementBindingModel::query()
            ->where('organization_id', $entity->organization_id)
            ->where('project_id', $entity->project_id)
            ->where('content_entity_id', $entity->id)
            ->where('announcement_id', $announcement->id)
            ->exists();

        if (! $bindingExists) {
            return ['ok' => false, 'reason' => 'announcement_not_bound_to_entity', 'entity_id' => null];
        }

        $now = now();

        DB::transaction(function () use (
            $entity,
            $announcement,
            $operatorUserId,
            $lifecycleStatus,
            $canonicalUrl,
            $now,
        ): void {
            $entity->fill([
                'verification_status' => 'verified',
                'last_verified_at' => $now,
                'verified_announcement_id' => $announcement->id,
                'verified_content_hash' => (string) $announcement->content_hash,
                'lifecycle_status' => $lifecycleStatus,
                'hub_member' => true,
                'publish_eligible' => true,
                'last_changed_at' => $now,
            ]);
            $entity->save();

            $remoteBinding = RemotePostBindingModel::query()
                ->where('organization_id', $entity->organization_id)
                ->where('project_id', $entity->project_id)
                ->where('content_entity_id', $entity->id)
                ->where('remote_system', 'wordpress')
                ->lockForUpdate()
                ->first();

            if ($remoteBinding === null) {
                RemotePostBindingModel::create([
                    'organization_id' => $entity->organization_id,
                    'project_id' => $entity->project_id,
                    'content_entity_id' => $entity->id,
                    'remote_system' => 'wordpress',
                    'remote_post_id' => null,
                    'canonical_url' => $canonicalUrl,
                    'slug' => null,
                    'confirmed_at' => $now,
                    'confirmed_by' => $operatorUserId,
                    'bound_at' => $now,
                    'last_synced_at' => null,
                ]);

                return;
            }

            $remoteBinding->canonical_url = $canonicalUrl;
            $remoteBinding->confirmed_at = $now;
            $remoteBinding->confirmed_by = $operatorUserId;
            $remoteBinding->save();
        });

        return [
            'ok' => true,
            'reason' => null,
            'entity_id' => (string) $entity->entity_id,
        ];
    }
}
