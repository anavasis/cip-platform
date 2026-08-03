<?php

namespace App\Modules\Editorial\Domain\PromptPackage;


/**
 * Blueprint identity reference bound into a Prompt Package.
 * Not the full ContentBlueprint aggregate.
 */
final class BlueprintReference
{
    private $blueprintId;
    private $blueprintRevision;
    private $announcementId;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data)
    {
        $this->blueprintId = isset($data['blueprint_id']) ? (string) $data['blueprint_id'] : '';
        $this->blueprintRevision = isset($data['blueprint_revision'])
            ? (int) $data['blueprint_revision']
            : 0;
        $this->announcementId = isset($data['announcement_id'])
            ? trim((string) $data['announcement_id'])
            : '';
    }

    /** @return string */
    public function blueprintId()
    {
        return $this->blueprintId;
    }

    /** @return string */
    public function blueprintRevision()
    {
        return $this->blueprintRevision;
    }

    /** @return string */
    public function announcementId()
    {
        return $this->announcementId;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray()
    {
        return array(
            'blueprint_id' => $this->blueprintId,
            'blueprint_revision' => $this->blueprintRevision,
            'announcement_id' => $this->announcementId,
        );
    }
}
