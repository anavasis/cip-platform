<?php

namespace App\Modules\Editorial\Infrastructure\Persistence\Models;

use App\Domain\Shared\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

class ArticlePreviewModel extends Model
{
    use HasUuid;

    protected $table = 'article_previews';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'created_at_utc' => 'datetime',
        ];
    }
}
