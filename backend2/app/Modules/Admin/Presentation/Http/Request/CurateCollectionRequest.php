<?php

declare(strict_types=1);

namespace App\Modules\Admin\Presentation\Http\Request;

use Illuminate\Foundation\Http\FormRequest;

final class CurateCollectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route is behind auth:admin
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'min:1', 'max:200'],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }
}
