<?php

namespace StudyMentor\ContentEngine\Article;

defined('ABSPATH') || exit;

/**
 * Process-local Article Preview store for Editorial Slice A.
 */
final class InMemoryArticlePreviewRepository implements ArticlePreviewRepositoryInterface
{
    /** @var array<string, ArticlePreview> */
    private $byId = array();
    /** @var array<int, string> */
    private $latestByAnnouncement = array();

    /**
     * @param ArticlePreview $preview
     * @return bool
     */
    public function save(ArticlePreview $preview)
    {
        if ($preview->previewId() === '' || $preview->announcementId() <= 0) {
            return false;
        }

        $this->byId[$preview->previewId()] = $preview;
        $this->latestByAnnouncement[$preview->announcementId()] = $preview->previewId();

        return true;
    }

    /**
     * @param string $previewId
     * @return ArticlePreview|null
     */
    public function findById($previewId)
    {
        $key = (string) $previewId;

        return isset($this->byId[$key]) ? $this->byId[$key] : null;
    }

    /**
     * @param int $announcementId
     * @return ArticlePreview|null
     */
    public function findLatestForAnnouncement($announcementId)
    {
        $id = (int) $announcementId;
        if ($id <= 0 || !isset($this->latestByAnnouncement[$id])) {
            return null;
        }

        return $this->findById($this->latestByAnnouncement[$id]);
    }
}
