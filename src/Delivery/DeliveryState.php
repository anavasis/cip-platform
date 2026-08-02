<?php

namespace StudyMentor\ContentEngine\Delivery;

defined('ABSPATH') || exit;

/**
 * Delivery Registry binding state vocabulary.
 */
final class DeliveryState
{
    public const PENDING = 'pending';
    public const DELIVERED = 'delivered';
    public const FAILED = 'failed';
    public const RETRY = 'retry';
    public const SKIPPED = 'skipped';
    public const ORPHANED = 'orphaned';

    private function __construct()
    {
    }

    /**
     * @param string $state
     * @return bool
     */
    public static function isValid($state)
    {
        return in_array(
            (string) $state,
            array(
                self::PENDING,
                self::DELIVERED,
                self::FAILED,
                self::RETRY,
                self::SKIPPED,
                self::ORPHANED,
            ),
            true
        );
    }
}
