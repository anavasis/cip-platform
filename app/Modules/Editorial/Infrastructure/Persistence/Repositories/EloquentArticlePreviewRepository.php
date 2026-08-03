<?php

namespace App\Modules\Editorial\Infrastructure\Persistence\Repositories;

use App\Modules\Editorial\Domain\Article\ArticlePreview;
use App\Modules\Editorial\Domain\Article\ArticlePreviewRepositoryInterface;
use App\Modules\Editorial\Infrastructure\Persistence\Models\ArticlePreviewModel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

final class EloquentArticlePreviewRepository implements ArticlePreviewRepositoryInterface
{
    public function save(ArticlePreview $preview): bool
    {
        if (
            $preview->previewId() === ''
            || $preview->organizationId() === ''
            || $preview->projectId() === ''
            || $preview->announcementId() === ''
        ) {
            return false;
        }

        try {
            $existing = ArticlePreviewModel::query()
                ->where('project_id', $preview->projectId())
                ->where('preview_key', $preview->previewId())
                ->first();
            if ($existing) {
                return true;
            }

            ArticlePreviewModel::query()->create([
                'id' => (string) Str::uuid(),
                'organization_id' => $preview->organizationId(),
                'project_id' => $preview->projectId(),
                'announcement_id' => $preview->announcementId(),
                'preview_id' => $preview->previewId(),
                'preview_key' => $preview->previewId(),
                'request_id' => $preview->requestId(),
                'result_id' => $preview->resultId(),
                'result_hash' => $preview->resultHash(),
                'title' => $preview->title(),
                'body' => $preview->body(),
                'created_at_utc' => $preview->createdAtUtc() !== ''
                    ? $preview->createdAtUtc()
                    : gmdate('Y-m-d H:i:s'),
            ]);

            return true;
        } catch (QueryException) {
            return ArticlePreviewModel::query()
                ->where('project_id', $preview->projectId())
                ->where(function ($q) use ($preview) {
                    $q->where('preview_id', $preview->previewId())
                        ->orWhere('preview_key', $preview->previewId());
                })
                ->exists();
        }
    }

    public function findById(string $organizationId, string $projectId, string $previewId): ?ArticlePreview
    {
        $row = ArticlePreviewModel::query()
            ->where('organization_id', $organizationId)
            ->where('project_id', $projectId)
            ->where('preview_id', $previewId)
            ->first();

        return $row ? $this->toDomain($row) : null;
    }

    public function findLatestForAnnouncement(
        string $organizationId,
        string $projectId,
        string $announcementId
    ): ?ArticlePreview {
        $row = ArticlePreviewModel::query()
            ->where('organization_id', $organizationId)
            ->where('project_id', $projectId)
            ->where('announcement_id', $announcementId)
            ->orderByDesc('created_at_utc')
            ->first();

        return $row ? $this->toDomain($row) : null;
    }

    public function findByPreviewKey(string $organizationId, string $projectId, string $previewKey): ?ArticlePreview
    {
        $row = ArticlePreviewModel::query()
            ->where('organization_id', $organizationId)
            ->where('project_id', $projectId)
            ->where('preview_key', $previewKey)
            ->first();

        return $row ? $this->toDomain($row) : null;
    }

    private function toDomain(ArticlePreviewModel $row): ArticlePreview
    {
        return new ArticlePreview([
            'preview_id' => $row->preview_id,
            'organization_id' => $row->organization_id,
            'project_id' => $row->project_id,
            'announcement_id' => $row->announcement_id,
            'request_id' => $row->request_id,
            'result_id' => $row->result_id,
            'result_hash' => $row->result_hash,
            'title' => $row->title,
            'body' => $row->body,
            'created_at_utc' => $row->created_at_utc
                ? $row->created_at_utc->utc()->format('Y-m-d H:i:s')
                : '',
        ]);
    }
}
