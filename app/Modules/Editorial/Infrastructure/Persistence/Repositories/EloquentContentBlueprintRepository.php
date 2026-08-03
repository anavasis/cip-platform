<?php

namespace App\Modules\Editorial\Infrastructure\Persistence\Repositories;

use App\Modules\Editorial\Domain\Blueprint\ContentBlueprint;
use App\Modules\Editorial\Domain\Blueprint\ContentBlueprintRepositoryInterface;
use App\Modules\Editorial\Infrastructure\Persistence\Models\ContentBlueprintModel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

final class EloquentContentBlueprintRepository implements ContentBlueprintRepositoryInterface
{
    public function save(string $organizationId, string $projectId, ContentBlueprint $blueprint): bool
    {
        if ($organizationId === '' || $projectId === '' || $blueprint->blueprintId() === '') {
            return false;
        }

        try {
            $row = ContentBlueprintModel::query()->firstOrNew([
                'project_id' => $projectId,
                'blueprint_id' => $blueprint->blueprintId(),
            ]);
            if (! $row->exists) {
                $row->id = (string) Str::uuid();
            }
            $row->fill([
                'organization_id' => $organizationId,
                'announcement_id' => $blueprint->announcementId(),
                'lineage_id' => $blueprint->lineageId() !== '' ? $blueprint->lineageId() : null,
                'blueprint_revision' => $blueprint->blueprintRevision(),
                'status' => $blueprint->status(),
                'article_type' => $blueprint->articleType(),
                'source_content_hash' => $blueprint->sourceContentHash() !== '' ? $blueprint->sourceContentHash() : null,
                'announcement_revision_no' => $blueprint->announcementRevisionNo(),
                'payload' => $blueprint->toArray(),
            ]);
            $row->save();

            return true;
        } catch (QueryException) {
            return ContentBlueprintModel::query()
                ->where('project_id', $projectId)
                ->where('blueprint_id', $blueprint->blueprintId())
                ->exists();
        }
    }

    public function findById(string $organizationId, string $projectId, string $blueprintId): ?ContentBlueprint
    {
        $row = ContentBlueprintModel::query()
            ->where('organization_id', $organizationId)
            ->where('project_id', $projectId)
            ->where('blueprint_id', $blueprintId)
            ->first();

        return $row ? new ContentBlueprint($row->payload ?? []) : null;
    }

    public function findLatestForAnnouncement(string $organizationId, string $projectId, string $announcementId): ?ContentBlueprint
    {
        $row = ContentBlueprintModel::query()
            ->where('organization_id', $organizationId)
            ->where('project_id', $projectId)
            ->where('announcement_id', $announcementId)
            ->orderByDesc('blueprint_revision')
            ->orderByDesc('created_at')
            ->first();

        return $row ? new ContentBlueprint($row->payload ?? []) : null;
    }
}
