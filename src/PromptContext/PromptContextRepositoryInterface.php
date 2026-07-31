<?php

namespace StudyMentor\ContentEngine\PromptContext;

defined('ABSPATH') || exit;

/**
 * Persistence port for Prompt Context snapshots.
 * BUILD-002 provides the interface only — no database adapter.
 */
interface PromptContextRepositoryInterface
{
    /**
     * @param PromptContext $context
     * @return bool
     */
    public function save(PromptContext $context);

    /**
     * @param string $contextId
     * @return PromptContext|null
     */
    public function findById($contextId);

    /**
     * Latest non-superseded context for an announcement, if any.
     *
     * @param int $announcementId
     * @return PromptContext|null
     */
    public function findLatestForAnnouncement($announcementId);

    /**
     * Latest context bound to a blueprint id, if any.
     *
     * @param string $blueprintId
     * @return PromptContext|null
     */
    public function findLatestForBlueprint($blueprintId);
}
