<?php

namespace StudyMentor\ContentEngine\Announcement;

defined('ABSPATH') || exit;

/**
 * Announcement lifecycle outcome vocabulary.
 */
final class LifecycleOutcome
{
    public const NEW_ITEM = 'new';
    public const UPDATED = 'updated';
    public const UNCHANGED = 'unchanged';
    public const DUPLICATE = 'duplicate';

    private function __construct()
    {
    }

    /**
     * @param string $outcome
     * @return bool
     */
    public static function isValid($outcome)
    {
        return in_array(
            (string) $outcome,
            array(self::NEW_ITEM, self::UPDATED, self::UNCHANGED, self::DUPLICATE),
            true
        );
    }
}
