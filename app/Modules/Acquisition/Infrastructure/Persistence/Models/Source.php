<?php

namespace App\Modules\Acquisition\Infrastructure\Persistence\Models;

use App\Domain\Shared\Concerns\HasUuid;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

class Source extends Model
{
    use HasUuid;

    protected $fillable = [
        'organization_id',
        'project_id',
        'slug',
        'name',
        'source_type',
        'base_url',
        'feed_url',
        'feed_url_hash',
        'allowed_domains',
        'parser_profile',
        'enabled',
        'manual_only',
        'acquire_interval_seconds',
        'last_acquired_at',
        'last_checked_at',
        'last_check_status',
    ];

    protected function casts(): array
    {
        return [
            'allowed_domains' => 'array',
            'enabled' => 'boolean',
            'manual_only' => 'boolean',
            'acquire_interval_seconds' => 'integer',
            'last_acquired_at' => 'datetime',
            'last_checked_at' => 'datetime',
        ];
    }

    public function isDueForAcquisition(?CarbonInterface $at = null): bool
    {
        $lastAcquired = $this->last_acquired_at;
        $lastChecked = $this->last_checked_at;
        $lastAttempt = $lastChecked !== null
            && ($lastAcquired === null || $lastChecked->greaterThan($lastAcquired))
                ? $lastChecked
                : $lastAcquired;

        if ($lastAttempt === null) {
            return true;
        }

        return $lastAttempt
            ->copy()
            ->addSeconds(max(1, (int) $this->acquire_interval_seconds))
            ->lessThanOrEqualTo($at ?? now());
    }
}
