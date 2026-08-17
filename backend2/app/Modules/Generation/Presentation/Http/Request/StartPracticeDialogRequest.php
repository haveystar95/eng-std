<?php

declare(strict_types=1);

namespace App\Modules\Generation\Presentation\Http\Request;

use Illuminate\Foundation\Http\FormRequest;

final class StartPracticeDialogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'collection_id' => ['required', 'string', 'size:26'],
            // Client-generated ULID — the dialog's id, so a retry is idempotent.
            'client_id' => ['required', 'string', 'size:26'],
        ];
    }
}
