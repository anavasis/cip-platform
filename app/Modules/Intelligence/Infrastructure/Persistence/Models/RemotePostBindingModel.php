<?php

namespace App\Modules\Intelligence\Infrastructure\Persistence\Models;

use App\Domain\Shared\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RemotePostBindingModel extends Model
{
    use HasUuid;

    protected $table = 'remote_post_bindings';

    protected $fillable = [
        'organization_id',
        'project_id',
        'content_entity_id',
        'remote_system',
        'remote_post_id',
        'canonical_url',
        'slug',
        'confirmed_at',
        'confirmed_by',
        'bound_at',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'confirmed_at' => 'datetime',
            'bound_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }

    public function contentEntity(): BelongsTo
    {
        return $this->belongsTo(ContentEntityModel::class, 'content_entity_id');
    }
}
