<?php

namespace App\Modules\Editorial\Domain\Article;

interface ArticlePreviewRepositoryInterface
{
    public function save(ArticlePreview $preview): bool;

    public function findById(string $organizationId, string $projectId, string $previewId): ?ArticlePreview;

    public function findLatestForAnnouncement(
        string $organizationId,
        string $projectId,
        string $announcementId
    ): ?ArticlePreview;

    public function findByPreviewKey(string $organizationId, string $projectId, string $previewKey): ?ArticlePreview;
}
