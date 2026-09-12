<?php

declare(strict_types=1);

namespace App\Modules\Identity\Presentation\Http\Request;

use Illuminate\Foundation\Http\FormRequest;

final class PushTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // the address is registered for the bearer's own account
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'platform' => ['required', 'string', 'in:ios'],
            'token' => ['required', 'string', 'min:1', 'max:255'],
            'locale' => ['sometimes', 'nullable', 'string', 'max:35'],
            'timezone' => ['sometimes', 'nullable', 'timezone:all_with_bc'], // same leniency as /profile
        ];
    }
}
