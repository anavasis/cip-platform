<?php

namespace StudyMentor\ContentEngine\Announcement;

defined('ABSPATH') || exit;

/**
 * Batch lifecycle result for diagnostics and future editorial consumers.
 */
final class LifecycleBatchResult
{
    private $success;
    private $errorCode;
    private $sourceId;
    private $candidates;
    private $newCount;
    private $updatedCount;
    private $unchangedCount;
    private $duplicateCount;
    /** @var array<int, LifecycleDecision> */
    private $decisions;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data)
    {
        $this->success = isset($data['success']) && $data['success'] === true;
        $this->errorCode = isset($data['error_code']) ? (string) $data['error_code'] : '';
        $this->sourceId = isset($data['source_id']) ? (int) $data['source_id'] : 0;
        $this->candidates = isset($data['candidates']) ? (int) $data['candidates'] : 0;
        $this->newCount = isset($data['new_count']) ? (int) $data['new_count'] : 0;
        $this->updatedCount = isset($data['updated_count']) ? (int) $data['updated_count'] : 0;
        $this->unchangedCount = isset($data['unchanged_count']) ? (int) $data['unchanged_count'] : 0;
        $this->duplicateCount = isset($data['duplicate_count']) ? (int) $data['duplicate_count'] : 0;
        $this->decisions = array();

        if (isset($data['decisions']) && is_array($data['decisions'])) {
            foreach ($data['decisions'] as $decision) {
                if ($decision instanceof LifecycleDecision) {
                    $this->decisions[] = $decision;
                }
            }
        }
    }

    /** @return bool */
    public function success()
    {
        return $this->success;
    }

    /** @return string */
    public function errorCode()
    {
        return $this->errorCode;
    }

    /** @return int */
    public function sourceId()
    {
        return $this->sourceId;
    }

    /** @return int */
    public function candidates()
    {
        return $this->candidates;
    }

    /** @return int */
    public function newCount()
    {
        return $this->newCount;
    }

    /** @return int */
    public function updatedCount()
    {
        return $this->updatedCount;
    }

    /** @return int */
    public function unchangedCount()
    {
        return $this->unchangedCount;
    }

    /** @return int */
    public function duplicateCount()
    {
        return $this->duplicateCount;
    }

    /**
     * @return array<int, LifecycleDecision>
     */
    public function decisions()
    {
        return $this->decisions;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray()
    {
        $decisions = array();

        foreach ($this->decisions as $decision) {
            $decisions[] = $decision->toArray();
        }

        return array(
            'success' => $this->success,
            'error_code' => $this->errorCode,
            'source_id' => $this->sourceId,
            'candidates' => $this->candidates,
            'new_count' => $this->newCount,
            'updated_count' => $this->updatedCount,
            'unchanged_count' => $this->unchangedCount,
            'duplicate_count' => $this->duplicateCount,
            'decisions' => $decisions,
        );
    }
}
