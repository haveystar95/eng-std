<?php

declare(strict_types=1);

namespace App\Modules\Generation\Presentation\Http\Request;

use Illuminate\Foundation\Http\FormRequest;

final class LookupWordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // 100 characters is a phrase, generously. Beyond that it is a sentence, and the lookup
            // card has nothing useful to say about one — the collection generator does.
            'query' => ['required', 'string', 'min:1', 'max:100'],
        ];
    }
}
