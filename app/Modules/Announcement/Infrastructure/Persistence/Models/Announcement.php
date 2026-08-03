<?php

namespace App\Modules\Announcement\Infrastructure\Persistence\Models;

use App\Domain\Shared\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

class Announcement extends Model
{
    use HasUuid;

    protected $fillable = [
        'organization_id',
        'project_id',
        'source_id',
        'identity_hash',
        'identity_basis',
        'source_guid',
        'canonical_url',
        'source_published_at',
        'raw_title',
        'content_hash',
        'raw_payload',
        'revision_no',
        'first_seen_at',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'source_published_at' => 'datetime',
            'raw_payload' => 'array',
            'revision_no' => 'integer',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }
}
