<?php

namespace App\Modules\Editorial\Domain\GenerationResult;


/**
 * Outcome status for a Generation Result (ADR-001).
 * Immutable outcome envelope — not queue/worker/provider execution states.
 */
final class GenerationResultStatus
{
    public const SUCCESS = 'success';
    public const ERROR = 'error';

    /**
     * @return array<int, string>
     */
    public static function all()
    {
        return array(
            self::SUCCESS,
            self::ERROR,
        );
    }

    /**
     * @param string $status
     * @return bool
     */
    public static function isValid($status)
    {
        return in_array((string) $status, self::all(), true);
    }
}
