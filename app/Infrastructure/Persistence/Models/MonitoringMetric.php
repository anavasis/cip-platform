<?php

namespace App\Infrastructure\Persistence\Models;

use App\Domain\Shared\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

class MonitoringMetric extends Model
{
    use HasUuid;

    public $timestamps = false;

    protected $fillable = [
        'name',
        'value',
        'tags',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:6',
            'tags' => 'array',
            'recorded_at' => 'datetime',
        ];
    }
}
