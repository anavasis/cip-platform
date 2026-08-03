<?php

namespace App\Modules\Editorial\Domain\Article;

interface ArticlePreviewRepositoryInterface
{
    public function save(ArticlePreview $preview): bool;

    public function findById(string $previewId): ?ArticlePreview;

    public function findLatestForAnnouncement(
        string $organizationId,
        string $projectId,
        string $announcementId
    ): ?ArticlePreview;
}
