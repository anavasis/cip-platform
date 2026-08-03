<?php

namespace App\Infrastructure\Persistence\Models;

use App\Domain\Shared\Concerns\HasUuid;
use App\Domain\Shared\Enums\FeatureFlagScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeatureFlag extends Model
{
    use HasUuid;

    protected $fillable = [
        'scope',
        'organization_id',
        'project_id',
        'key',
        'value',
        'enabled',
    ];

    protected function casts(): array
    {
        return [
            'scope' => FeatureFlagScope::class,
            'value' => 'array',
            'enabled' => 'boolean',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
