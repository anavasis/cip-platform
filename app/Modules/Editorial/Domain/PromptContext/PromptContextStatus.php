<?php

namespace App\Modules\Editorial\Domain\PromptContext;


/**
 * Lifecycle status for a Prompt Context snapshot (ADR-001).
 */
final class PromptContextStatus
{
    public const DRAFT = 'draft';
    public const READY = 'ready';
    public const SUPERSEDED = 'superseded';

    /**
     * @return array<int, string>
     */
    public static function all()
    {
        return array(
            self::DRAFT,
            self::READY,
            self::SUPERSEDED,
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
