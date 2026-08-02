<?php

namespace StudyMentor\ContentEngine\GenerationRequest;

defined('ABSPATH') || exit;

/**
 * Lifecycle status for a Generation Request (ADR-001).
 * Intent states only — no queue/worker/provider execution semantics.
 */
final class GenerationRequestStatus
{
    public const DRAFT = 'draft';
    public const READY = 'ready';
    public const SUPERSEDED = 'superseded';
    public const CANCELLED = 'cancelled';

    /**
     * @return array<int, string>
     */
    public static function all()
    {
        return array(
            self::DRAFT,
            self::READY,
            self::SUPERSEDED,
            self::CANCELLED,
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
