<?php

namespace StudyMentor\ContentEngine\Announcement;

defined('ABSPATH') || exit;

/**
 * Durable editorial status labels derived from stored revision facts.
 * NEW = revision_no 1; UPDATED = revision_no greater than 1.
 */
final class EditorialWorkspaceQueryService
{
    /**
     * @param int $revisionNo
     * @return string
     */
    public function statusFromRevision($revisionNo)
    {
        return ((int) $revisionNo) > 1 ? 'UPDATED' : 'NEW';
    }
}
