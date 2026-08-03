<?php

namespace App\Modules\Editorial\Domain\PromptContext;


/**
 * Canonical Prompt Context aggregate (ADR-001).
 * Provider-independent structured facts snapshot — not prompt prose.
 */
final class PromptContext
{
    private $contextId;
    private $announcementId;
    private $blueprintId;
    private $blueprintRevision;
    private $sourceContentHash;
    private $announcementRevisionNo;
    private $status;
    private $facts;
    private $blueprintProjection;
    private $contextHash;
    private $createdAtUtc;
    private $updatedAtUtc;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data)
    {
        $this->contextId = isset($data['context_id']) ? (string) $data['context_id'] : '';
        $this->announcementId = isset($data['announcement_id']) ? trim((string) $data['announcement_id']) : '';
        $this->blueprintId = isset($data['blueprint_id']) ? (string) $data['blueprint_id'] : '';
        $this->blueprintRevision = isset($data['blueprint_revision'])
            ? (int) $data['blueprint_revision']
            : 1;
        $this->sourceContentHash = isset($data['source_content_hash'])
            ? (string) $data['source_content_hash']
            : '';
        $this->announcementRevisionNo = isset($data['announcement_revision_no'])
            ? (int) $data['announcement_revision_no']
            : 1;
        $this->status = isset($data['status']) ? (string) $data['status'] : PromptContextStatus::DRAFT;
        $this->facts = isset($data['facts']) && $data['facts'] instanceof AnnouncementFacts
            ? $data['facts']
            : new AnnouncementFacts(
                isset($data['facts']) && is_array($data['facts']) ? $data['facts'] : array()
            );
        $this->blueprintProjection = isset($data['blueprint_projection'])
            && $data['blueprint_projection'] instanceof BlueprintProjection
            ? $data['blueprint_projection']
            : new BlueprintProjection(
                isset($data['blueprint_projection']) && is_array($data['blueprint_projection'])
                    ? $data['blueprint_projection']
                    : array()
            );
        $this->contextHash = isset($data['context_hash']) ? (string) $data['context_hash'] : '';
        $this->createdAtUtc = isset($data['created_at_utc']) ? (string) $data['created_at_utc'] : '';
        $this->updatedAtUtc = isset($data['updated_at_utc']) ? (string) $data['updated_at_utc'] : '';
    }

    /** @return string */
    public function contextId()
    {
        return $this->contextId;
    }

    /** @return string */
    public function announcementId()
    {
        return $this->announcementId;
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
    public function sourceContentHash()
    {
        return $this->sourceContentHash;
    }

    /** @return string */
    public function announcementRevisionNo()
    {
        return $this->announcementRevisionNo;
    }

    /** @return string */
    public function status()
    {
        return $this->status;
    }

    /** @return AnnouncementFacts */
    public function facts()
    {
        return $this->facts;
    }

    /** @return BlueprintProjection */
    public function blueprintProjection()
    {
        return $this->blueprintProjection;
    }

    /** @return string */
    public function contextHash()
    {
        return $this->contextHash;
    }

    /** @return string */
    public function createdAtUtc()
    {
        return $this->createdAtUtc;
    }

    /** @return string */
    public function updatedAtUtc()
    {
        return $this->updatedAtUtc;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray()
    {
        return array(
            'context_id' => $this->contextId,
            'announcement_id' => $this->announcementId,
            'blueprint_id' => $this->blueprintId,
            'blueprint_revision' => $this->blueprintRevision,
            'source_content_hash' => $this->sourceContentHash,
            'announcement_revision_no' => $this->announcementRevisionNo,
            'status' => $this->status,
            'facts' => $this->facts->toArray(),
            'blueprint_projection' => $this->blueprintProjection->toArray(),
            'context_hash' => $this->contextHash,
            'created_at_utc' => $this->createdAtUtc,
            'updated_at_utc' => $this->updatedAtUtc,
        );
    }
}
