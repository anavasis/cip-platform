<?php

namespace App\Infrastructure\Persistence\Models;

use App\Domain\Shared\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectConnector extends Model
{
    use HasUuid;

    protected $fillable = [
        'organization_id',
        'project_id',
        'connector_type_id',
        'name',
        'config',
        'enabled',
    ];

    protected function casts(): array
    {
        return [
            'config' => 'array',
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

    public function connectorType(): BelongsTo
    {
        return $this->belongsTo(ConnectorType::class);
    }
}
