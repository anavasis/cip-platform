<?php

namespace StudyMentor\ContentEngine\GenerationResult;

defined('ABSPATH') || exit;

/**
 * Persistence port for Generation Results.
 * BUILD-005 provides the interface only — no database adapter.
 */
interface GenerationResultRepositoryInterface
{
    /**
     * @param GenerationResult $result
     * @return bool
     */
    public function save(GenerationResult $result);

    /**
     * @param string $resultId
     * @return GenerationResult|null
     */
    public function findById($resultId);

    /**
     * @param string $resultHash
     * @return GenerationResult|null
     */
    public function findByResultHash($resultHash);

    /**
     * Result for a Generation Request id, if any (at most one successful outcome expected).
     *
     * @param string $requestId
     * @return GenerationResult|null
     */
    public function findByRequestId($requestId);

    /**
     * Latest result for an announcement, if any.
     *
     * @param int $announcementId
     * @return GenerationResult|null
     */
    public function findLatestForAnnouncement($announcementId);
}
