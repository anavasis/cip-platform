<?php

namespace App\Modules\Editorial\Infrastructure\Persistence\Models;

use App\Domain\Shared\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

class ContentBlueprintModel extends Model
{
    use HasUuid;

    protected $table = 'content_blueprints';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }
}
