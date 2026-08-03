<?php

namespace App\Modules\Announcement\Application;

/**
 * Durable editorial status labels derived from stored revision facts.
 */
final class EditorialWorkspaceQueryService
{
    public function statusFromRevision(int $revisionNo): string
    {
        return $revisionNo > 1 ? 'UPDATED' : 'NEW';
    }
}
