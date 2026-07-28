<?php

declare(strict_types=1);

namespace App\Modules\Collections\Presentation\Http\Request;

use Illuminate\Foundation\Http\FormRequest;

final class CreateCollectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'id' => ['sometimes', 'string', 'size:26'],   // client-generated ULID (offline idempotency)
            'title' => ['required', 'string', 'min:1', 'max:200'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'source_lang' => ['sometimes', 'string', 'min:2', 'max:5'],
            'target_lang' => ['sometimes', 'string', 'min:2', 'max:5'],
        ];
    }
}
