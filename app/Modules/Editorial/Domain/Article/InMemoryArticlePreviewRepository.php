<?php

namespace App\Modules\Editorial\Domain\Article;

/**
 * Process-local Article Preview store for isolated unit tests only.
 */
final class InMemoryArticlePreviewRepository implements ArticlePreviewRepositoryInterface
{
    /** @var array<string, ArticlePreview> */
    private array $byId = [];

    /** @var array<string, string> */
    private array $latestByScope = [];

    public function save(ArticlePreview $preview): bool
    {
        if ($preview->previewId() === '' || $preview->announcementId() === '') {
            return false;
        }

        $this->byId[$preview->previewId()] = $preview;
        $this->latestByScope[$this->scopeKey(
            $preview->organizationId(),
            $preview->projectId(),
            $preview->announcementId()
        )] = $preview->previewId();

        return true;
    }

    public function findById(string $previewId): ?ArticlePreview
    {
        return $this->byId[$previewId] ?? null;
    }

    public function findLatestForAnnouncement(
        string $organizationId,
        string $projectId,
        string $announcementId
    ): ?ArticlePreview {
        $key = $this->scopeKey($organizationId, $projectId, $announcementId);
        if (! isset($this->latestByScope[$key])) {
            return null;
        }

        return $this->findById($this->latestByScope[$key]);
    }

    private function scopeKey(string $organizationId, string $projectId, string $announcementId): string
    {
        return $organizationId.'|'.$projectId.'|'.$announcementId;
    }
}
