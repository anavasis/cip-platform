<?php

namespace App\Modules\Intelligence\Infrastructure\Persistence\Models;

use App\Domain\Shared\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContentEntityModel extends Model
{
    use HasUuid;

    protected $table = 'content_entities';

    protected $fillable = [
        'organization_id',
        'project_id',
        'entity_id',
        'entity_type',
        'label',
        'code',
        'organization_body',
        'source_family',
        'thematic_categories',
        'content_role',
        'lifecycle_status',
        'application_open_at',
        'application_deadline_at',
        'positions_count',
        'next_step_label',
        'verification_status',
        'last_verified_at',
        'last_changed_at',
        'hub_display_section',
        'hub_member',
        'archive_state',
        'publish_eligible',
        'verified_announcement_id',
        'verified_content_hash',
    ];

    protected function casts(): array
    {
        return [
            'thematic_categories' => 'array',
            'application_open_at' => 'datetime',
            'application_deadline_at' => 'datetime',
            'positions_count' => 'integer',
            'last_verified_at' => 'datetime',
            'last_changed_at' => 'datetime',
            'hub_member' => 'boolean',
            'publish_eligible' => 'boolean',
        ];
    }

    public function announcementBindings(): HasMany
    {
        return $this->hasMany(EntityAnnouncementBindingModel::class, 'content_entity_id');
    }

    public function remotePostBindings(): HasMany
    {
        return $this->hasMany(RemotePostBindingModel::class, 'content_entity_id');
    }
}
