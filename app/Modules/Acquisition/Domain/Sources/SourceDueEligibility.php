<?php

namespace App\Modules\Acquisition\Domain\Sources;

use App\Modules\Acquisition\Infrastructure\Persistence\Models\Source;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * Single canonical due-eligibility policy for acquisition sources.
 * Repository findDue() and AcquireDueSourcesJob must both use this.
 */
final class SourceDueEligibility
{
    public const DEFAULT_LIMIT = 500;

    public static function isDue(Source $source, ?CarbonInterface $at = null): bool
    {
        return $source->isDueForAcquisition($at);
    }

    /**
     * Constrains to enabled, non-manual sources whose acquire interval has elapsed.
     *
     * @param  Builder<Source>  $query
     * @return Builder<Source>
     */
    public static function constrainEligible(Builder $query, ?CarbonInterface $at = null): Builder
    {
        $query
            ->where('enabled', true)
            ->where('manual_only', false);

        return self::constrainIntervalDue($query, $at);
    }

    /**
     * Interval due filter only (caller supplies enabled/manual predicates if needed).
     *
     * @param  Builder<Source>  $query
     * @return Builder<Source>
     */
    public static function constrainIntervalDue(Builder $query, ?CarbonInterface $at = null): Builder
    {
        unset($at);
        $driver = $query->getConnection()->getDriverName();
        $latestAttempt = match ($driver) {
            'sqlite' => <<<'SQL'
                datetime(
                    CASE
                        WHEN last_acquired_at IS NULL THEN last_checked_at
                        WHEN last_checked_at IS NULL THEN last_acquired_at
                        WHEN last_acquired_at >= last_checked_at THEN last_acquired_at
                        ELSE last_checked_at
                    END,
                    '+' || acquire_interval_seconds || ' seconds'
                )
                SQL,
            'pgsql' => <<<'SQL'
                GREATEST(
                    COALESCE(last_acquired_at, last_checked_at),
                    COALESCE(last_checked_at, last_acquired_at)
                ) + (acquire_interval_seconds * INTERVAL '1 second')
                SQL,
            default => <<<'SQL'
                DATE_ADD(
                    GREATEST(
                        COALESCE(last_acquired_at, last_checked_at),
                        COALESCE(last_checked_at, last_acquired_at)
                    ),
                    INTERVAL acquire_interval_seconds SECOND
                )
                SQL,
        };

        return $query->where(function (Builder $due) use ($latestAttempt): void {
            $due->where(function (Builder $neverAttempted): void {
                $neverAttempted
                    ->whereNull('last_acquired_at')
                    ->whereNull('last_checked_at');
            })->orWhereRaw("({$latestAttempt}) <= CURRENT_TIMESTAMP");
        });
    }
}
