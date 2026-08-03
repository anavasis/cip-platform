<?php

namespace App\Modules\Acquisition\Infrastructure\Persistence\Models;

use App\Domain\Shared\Concerns\HasUuid;
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
            'last_acquired_at' => 'datetime',
            'last_checked_at' => 'datetime',
        ];
    }
}
