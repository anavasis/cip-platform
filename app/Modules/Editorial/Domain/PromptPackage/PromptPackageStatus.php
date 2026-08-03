<?php

namespace App\Modules\Editorial\Domain\PromptPackage;


/**
 * Lifecycle status for a Prompt Package (ADR-001).
 * Sealed packages are immutable bindings ready for Generation Request.
 */
final class PromptPackageStatus
{
    public const DRAFT = 'draft';
    public const SEALED = 'sealed';
    public const SUPERSEDED = 'superseded';

    /**
     * @return array<int, string>
     */
    public static function all()
    {
        return array(
            self::DRAFT,
            self::SEALED,
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
