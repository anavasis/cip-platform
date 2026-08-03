<?php

namespace App\Modules\Acquisition\Infrastructure\Persistence\Models;

use App\Domain\Shared\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

class AcquisitionRun extends Model
{
    use HasUuid;

    protected $fillable = [
        'organization_id',
        'project_id',
        'run_id',
        'status',
        'error_code',
        'sources_requested',
        'sources_succeeded',
        'sources_failed',
        'duration_ms',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'sources_requested' => 'integer',
            'sources_succeeded' => 'integer',
            'sources_failed' => 'integer',
            'duration_ms' => 'float',
            'meta' => 'array',
        ];
    }
}
