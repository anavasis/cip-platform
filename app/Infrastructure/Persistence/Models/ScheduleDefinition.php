<?php

namespace App\Infrastructure\Persistence\Models;

use App\Domain\Shared\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduleDefinition extends Model
{
    use HasUuid;

    protected $fillable = [
        'organization_id',
        'project_id',
        'name',
        'cron_expression',
        'job_type',
        'payload',
        'enabled',
        'last_run_at',
        'next_run_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'enabled' => 'boolean',
            'last_run_at' => 'datetime',
            'next_run_at' => 'datetime',
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
