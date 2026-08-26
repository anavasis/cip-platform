<?php

namespace App\Modules\Intelligence\Infrastructure\Persistence\Models;

use App\Domain\Shared\Concerns\HasUuid;
use App\Modules\Announcement\Infrastructure\Persistence\Models\Announcement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EntityAnnouncementBindingModel extends Model
{
    use HasUuid;

    protected $table = 'entity_announcement_bindings';

    protected $fillable = [
        'organization_id',
        'project_id',
        'content_entity_id',
        'announcement_id',
        'binding_role',
        'source_revision_at_bind',
        'content_hash_at_bind',
        'bound_at',
    ];

    protected function casts(): array
    {
        return [
            'source_revision_at_bind' => 'integer',
            'bound_at' => 'datetime',
        ];
    }

    public function contentEntity(): BelongsTo
    {
        return $this->belongsTo(ContentEntityModel::class, 'content_entity_id');
    }

    public function announcement(): BelongsTo
    {
        return $this->belongsTo(Announcement::class, 'announcement_id');
    }
}
