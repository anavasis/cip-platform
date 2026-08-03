<?php

namespace App\Modules\Editorial\Infrastructure\Persistence\Models;

use App\Domain\Shared\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

class GenerationResultModel extends Model
{
    use HasUuid;

    protected $table = 'generation_results';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }
}
