<?php

namespace StudyMentor\ContentEngine\Delivery;

defined('ABSPATH') || exit;

/**
 * In-memory Delivery binding store (process-local).
 * Canonical key: project_id + announcement_id + target.
 */
final class DeliveryRegistry
{
    /** @var array<string, DeliveryBinding> */
    private $byCompositeKey = array();

    /** @var array<string, string> */
    private $compositeByIdempotencyKey = array();

    /**
     * @param DeliveryBinding $binding
     * @return bool
     */
    public function save(DeliveryBinding $binding)
    {
        if (
            $binding->projectId() === ''
            || $binding->announcementId() <= 0
            || $binding->target() === ''
            || $binding->idempotencyKey() === ''
        ) {
            return false;
        }

        $key = $this->compositeKey(
            $binding->projectId(),
            $binding->announcementId(),
            $binding->target()
        );

        $this->byCompositeKey[$key] = $binding;
        $this->compositeByIdempotencyKey[$binding->idempotencyKey()] = $key;

        return true;
    }

    /**
     * @param string $projectId
     * @param int $announcementId
     * @param string $target
     * @return DeliveryBinding|null
     */
    public function find($projectId, $announcementId, $target)
    {
        $key = $this->compositeKey($projectId, $announcementId, $target);

        return isset($this->byCompositeKey[$key]) ? $this->byCompositeKey[$key] : null;
    }

    /**
     * @param string $idempotencyKey
     * @return DeliveryBinding|null
     */
    public function findByIdempotencyKey($idempotencyKey)
    {
        $normalized = (string) $idempotencyKey;

        if ($normalized === '' || !isset($this->compositeByIdempotencyKey[$normalized])) {
            return null;
        }

        $composite = $this->compositeByIdempotencyKey[$normalized];

        return isset($this->byCompositeKey[$composite]) ? $this->byCompositeKey[$composite] : null;
    }

    /**
     * @return int
     */
    public function count()
    {
        return count($this->byCompositeKey);
    }

    /**
     * @return array<int, DeliveryBinding>
     */
    public function all()
    {
        return array_values($this->byCompositeKey);
    }

    /**
     * @param string $projectId
     * @param int $announcementId
     * @param string $target
     * @return string
     */
    private function compositeKey($projectId, $announcementId, $target)
    {
        return (string) $projectId
            . "\0"
            . (string) (int) $announcementId
            . "\0"
            . (string) $target;
    }
}
