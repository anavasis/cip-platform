<?php

namespace StudyMentor\ContentEngine\Article;

defined('ABSPATH') || exit;

/**
 * Preview persistence port. Slice A ships in-memory adapter only.
 */
interface ArticlePreviewRepositoryInterface
{
    /**
     * @param ArticlePreview $preview
     * @return bool
     */
    public function save(ArticlePreview $preview);

    /**
     * @param string $previewId
     * @return ArticlePreview|null
     */
    public function findById($previewId);

    /**
     * Latest preview for an announcement, if any.
     *
     * @param int $announcementId
     * @return ArticlePreview|null
     */
    public function findLatestForAnnouncement($announcementId);
}
