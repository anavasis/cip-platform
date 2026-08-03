<?php

namespace App\Modules\Editorial\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GenerateArticlePreviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'async' => ['sometimes', 'boolean'],
            'correlation_id' => ['sometimes', 'nullable', 'string', 'max:128'],
        ];
    }
}
