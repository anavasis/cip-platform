<?php

namespace StudyMentor\ContentEngine\Delivery;

defined('ABSPATH') || exit;

/**
 * One Delivery Registry binding: announcement × target → external identity + state.
 */
final class DeliveryBinding
{
    private $projectId;
    private $announcementId;
    private $target;
    private $externalId;
    private $deliveryState;
    private $revision;
    private $idempotencyKey;
    private $lastSync;
    private $attemptCount;
    private $lastError;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data)
    {
        $this->projectId = isset($data['project_id']) ? (string) $data['project_id'] : '';
        $this->announcementId = isset($data['announcement_id']) ? (int) $data['announcement_id'] : 0;
        $this->target = isset($data['target']) ? (string) $data['target'] : '';
        $this->externalId = isset($data['external_id']) ? (string) $data['external_id'] : '';
        $this->deliveryState = isset($data['delivery_state'])
            ? (string) $data['delivery_state']
            : DeliveryState::PENDING;
        $this->revision = isset($data['revision']) ? (int) $data['revision'] : 0;
        $this->idempotencyKey = isset($data['idempotency_key'])
            ? (string) $data['idempotency_key']
            : '';
        $this->lastSync = isset($data['last_sync']) ? (string) $data['last_sync'] : '';
        $this->attemptCount = isset($data['attempt_count']) ? (int) $data['attempt_count'] : 0;
        $this->lastError = isset($data['last_error']) ? (string) $data['last_error'] : '';
    }

    /** @return string */
    public function projectId()
    {
        return $this->projectId;
    }

    /** @return int */
    public function announcementId()
    {
        return $this->announcementId;
    }

    /** @return string */
    public function target()
    {
        return $this->target;
    }

    /** @return string */
    public function externalId()
    {
        return $this->externalId;
    }

    /** @return string */
    public function deliveryState()
    {
        return $this->deliveryState;
    }

    /** @return int */
    public function revision()
    {
        return $this->revision;
    }

    /** @return string */
    public function idempotencyKey()
    {
        return $this->idempotencyKey;
    }

    /** @return string */
    public function lastSync()
    {
        return $this->lastSync;
    }

    /** @return int */
    public function attemptCount()
    {
        return $this->attemptCount;
    }

    /** @return string */
    public function lastError()
    {
        return $this->lastError;
    }

    /**
     * @param array<string, mixed> $overrides
     * @return self
     */
    public function with(array $overrides)
    {
        return new self(array_merge($this->toArray(), $overrides));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray()
    {
        return array(
            'project_id' => $this->projectId,
            'announcement_id' => $this->announcementId,
            'target' => $this->target,
            'external_id' => $this->externalId,
            'delivery_state' => $this->deliveryState,
            'revision' => $this->revision,
            'idempotency_key' => $this->idempotencyKey,
            'last_sync' => $this->lastSync,
            'attempt_count' => $this->attemptCount,
            'last_error' => $this->lastError,
        );
    }
}
