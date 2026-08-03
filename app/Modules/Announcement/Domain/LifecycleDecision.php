<?php

namespace App\Modules\Announcement\Domain;

/**
 * Per-announcement lifecycle decision record containing metadata only.
 */
final class LifecycleDecision
{
    private readonly string $outcome;

    private readonly string $sourceId;

    private readonly string $identityHash;

    private readonly string $contentHash;

    private readonly int $revisionNo;

    private readonly string $itemId;

    private readonly string $title;

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(array $data)
    {
        $this->outcome = isset($data['outcome']) ? (string) $data['outcome'] : '';
        $this->sourceId = isset($data['source_id']) ? (string) $data['source_id'] : '';
        $this->identityHash = isset($data['identity_hash']) ? (string) $data['identity_hash'] : '';
        $this->contentHash = isset($data['content_hash']) ? (string) $data['content_hash'] : '';
        $this->revisionNo = isset($data['revision_no']) ? (int) $data['revision_no'] : 0;
        $this->itemId = isset($data['item_id']) ? (string) $data['item_id'] : '';
        $this->title = isset($data['title']) ? (string) $data['title'] : '';
    }

    public function outcome(): string
    {
        return $this->outcome;
    }

    public function sourceId(): string
    {
        return $this->sourceId;
    }

    public function identityHash(): string
    {
        return $this->identityHash;
    }

    public function contentHash(): string
    {
        return $this->contentHash;
    }

    public function revisionNo(): int
    {
        return $this->revisionNo;
    }

    public function itemId(): string
    {
        return $this->itemId;
    }

    public function title(): string
    {
        return $this->title;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'outcome' => $this->outcome,
            'source_id' => $this->sourceId,
            'identity_hash' => $this->identityHash,
            'content_hash' => $this->contentHash,
            'revision_no' => $this->revisionNo,
            'item_id' => $this->itemId,
            'title' => $this->title,
        ];
    }
}
