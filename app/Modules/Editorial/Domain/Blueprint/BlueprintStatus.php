<?php

namespace App\Modules\Editorial\Domain\Blueprint;


/**
 * Blueprint lifecycle status vocabulary (ADR-001).
 */
final class BlueprintStatus
{
    public const DRAFT = 'draft';
    public const READY = 'ready';
    public const SUPERSEDED = 'superseded';
    public const LOCKED = 'locked';

    private function __construct()
    {
    }

    /**
     * @param string $status
     * @return bool
     */
    public static function isValid($status)
    {
        return in_array(
            (string) $status,
            array(self::DRAFT, self::READY, self::SUPERSEDED, self::LOCKED),
            true
        );
    }

    /**
     * @return array<int, string>
     */
    public static function all()
    {
        return array(self::DRAFT, self::READY, self::SUPERSEDED, self::LOCKED);
    }
}
