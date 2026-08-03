<?php

namespace App\Modules\Acquisition\Infrastructure\Persistence\Models;

use App\Domain\Shared\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

class AcquisitionRunItem extends Model
{
    use HasUuid;

    protected $fillable = [
        'acquisition_run_id',
        'organization_id',
        'project_id',
        'source_id',
        'success',
        'error_code',
        'result_meta',
    ];

    protected function casts(): array
    {
        return [
            'success' => 'boolean',
            'result_meta' => 'array',
        ];
    }
}
