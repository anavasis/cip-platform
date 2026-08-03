<?php

namespace App\Modules\Editorial\Infrastructure\Persistence\Models;

use App\Domain\Shared\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

class GenerationRequestModel extends Model
{
    use HasUuid;

    protected $table = 'generation_requests';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }
}
