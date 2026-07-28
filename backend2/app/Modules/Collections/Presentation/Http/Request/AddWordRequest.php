<?php

declare(strict_types=1);

namespace App\Modules\Collections\Presentation\Http\Request;

use Illuminate\Foundation\Http\FormRequest;

final class AddWordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'text' => ['required', 'string', 'min:1', 'max:200'],
            'translation' => ['required', 'string', 'min:1', 'max:500'],
            'type' => ['sometimes', 'string', 'in:word,phrase'],
        ];
    }
}
