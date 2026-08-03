<?php

namespace App\Modules\Editorial\Domain\Blueprint;


/**
 * Tone vocabulary for generated content intent.
 */
final class Tone
{
    public const NEUTRAL = 'neutral';
    public const FORMAL = 'formal';
    public const ACCESSIBLE = 'accessible';

    private function __construct()
    {
    }

    /**
     * @param string $tone
     * @return bool
     */
    public static function isValid($tone)
    {
        return in_array((string) $tone, self::all(), true);
    }

    /**
     * @return array<int, string>
     */
    public static function all()
    {
        return array(self::NEUTRAL, self::FORMAL, self::ACCESSIBLE);
    }
}
