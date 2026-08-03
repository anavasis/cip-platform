<?php

namespace App\Modules\Editorial\Domain\Blueprint;


/**
 * Persistence port for Content Blueprints.
 * BUILD-001 provides the interface only — no database adapter.
 */
interface ContentBlueprintRepositoryInterface
{
    /**
     * @param ContentBlueprint $blueprint
     * @return bool
     */
    public function save(ContentBlueprint $blueprint);

    /**
     * @param string $blueprintId
     * @return ContentBlueprint|null
     */
    public function findById($blueprintId);

    /**
     * Latest non-superseded blueprint for an announcement, if any.
     *
     * @param string $announcementId
     * @return ContentBlueprint|null
     */
    public function findLatestForAnnouncement($announcementId);
}
