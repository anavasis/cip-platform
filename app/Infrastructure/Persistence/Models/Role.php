<?php

namespace App\Infrastructure\Persistence\Models;

use App\Domain\Shared\Concerns\HasUuid;
use App\Domain\Shared\Enums\RoleScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    use HasUuid;

    protected $fillable = [
        'name',
        'scope',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'scope' => RoleScope::class,
        ];
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permission');
    }

    public function organizationMemberships(): HasMany
    {
        return $this->hasMany(OrganizationMembership::class);
    }

    public function projectMemberships(): HasMany
    {
        return $this->hasMany(ProjectMembership::class);
    }
}
