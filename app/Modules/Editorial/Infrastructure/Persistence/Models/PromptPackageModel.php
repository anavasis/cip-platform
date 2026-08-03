<?php

namespace App\Modules\Editorial\Infrastructure\Persistence\Models;

use App\Domain\Shared\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

class PromptPackageModel extends Model
{
    use HasUuid;

    protected $table = 'prompt_packages';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'sealed_at_utc' => 'datetime',
        ];
    }
}
