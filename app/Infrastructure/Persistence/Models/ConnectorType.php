<?php

namespace App\Infrastructure\Persistence\Models;

use App\Domain\Shared\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConnectorType extends Model
{
    use HasUuid;

    protected $fillable = [
        'type',
        'name',
        'description',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function projectConnectors(): HasMany
    {
        return $this->hasMany(ProjectConnector::class);
    }
}
