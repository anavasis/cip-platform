<?php

namespace StudyMentor\ContentEngine\Announcement;

defined('ABSPATH') || exit;

/**
 * Per-announcement lifecycle decision record (metadata only).
 */
final class LifecycleDecision
{
    private $outcome;
    private $sourceId;
    private $identityHash;
    private $contentHash;
    private $revisionNo;
    private $itemId;
    private $title;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data)
    {
        $this->outcome = isset($data['outcome']) ? (string) $data['outcome'] : '';
        $this->sourceId = isset($data['source_id']) ? (int) $data['source_id'] : 0;
        $this->identityHash = isset($data['identity_hash']) ? (string) $data['identity_hash'] : '';
        $this->contentHash = isset($data['content_hash']) ? (string) $data['content_hash'] : '';
        $this->revisionNo = isset($data['revision_no']) ? (int) $data['revision_no'] : 0;
        $this->itemId = isset($data['item_id']) ? (int) $data['item_id'] : 0;
        $this->title = isset($data['title']) ? (string) $data['title'] : '';
    }

    /** @return string */
    public function outcome()
    {
        return $this->outcome;
    }

    /** @return int */
    public function sourceId()
    {
        return $this->sourceId;
    }

    /** @return string */
    public function identityHash()
    {
        return $this->identityHash;
    }

    /** @return string */
    public function contentHash()
    {
        return $this->contentHash;
    }

    /** @return int */
    public function revisionNo()
    {
        return $this->revisionNo;
    }

    /** @return int */
    public function itemId()
    {
        return $this->itemId;
    }

    /** @return string */
    public function title()
    {
        return $this->title;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray()
    {
        return array(
            'outcome' => $this->outcome,
            'source_id' => $this->sourceId,
            'identity_hash' => $this->identityHash,
            'content_hash' => $this->contentHash,
            'revision_no' => $this->revisionNo,
            'item_id' => $this->itemId,
            'title' => $this->title,
        );
    }
}
