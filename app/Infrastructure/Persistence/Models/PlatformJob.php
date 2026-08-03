<?php

namespace App\Infrastructure\Persistence\Models;

use App\Domain\Shared\Concerns\HasUuid;
use App\Domain\Shared\Enums\PlatformJobStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformJob extends Model
{
    use HasUuid;

    protected $fillable = [
        'organization_id',
        'project_id',
        'job_type',
        'status',
        'payload',
        'result',
        'error',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => PlatformJobStatus::class,
            'payload' => 'array',
            'result' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
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
