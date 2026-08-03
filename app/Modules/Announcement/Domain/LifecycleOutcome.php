<?php

namespace App\Modules\Announcement\Domain;

final class LifecycleOutcome
{
    public const NEW_ITEM = 'new';

    public const UPDATED = 'updated';

    public const UNCHANGED = 'unchanged';

    public const DUPLICATE = 'duplicate';

    private function __construct() {}

    public static function isValid(string $outcome): bool
    {
        return in_array(
            $outcome,
            [self::NEW_ITEM, self::UPDATED, self::UNCHANGED, self::DUPLICATE],
            true,
        );
    }
}
