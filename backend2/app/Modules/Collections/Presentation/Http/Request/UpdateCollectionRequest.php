<?php

declare(strict_types=1);

namespace App\Modules\Collections\Presentation\Http\Request;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateCollectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'min:1', 'max:200'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
