<?php

declare(strict_types=1);

namespace App\Modules\Identity\Presentation\Http\Request;

use Illuminate\Foundation\Http\FormRequest;

final class GoogleLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'id_token' => ['required', 'string'],
            'device_name' => ['sometimes', 'string', 'max:100'],
            // Device IANA zone → seeds the profile for F19 due rounding. `all_with_bc` because a
            // device may still report a LEGACY alias (iOS sends `Europe/Kiev`, not `Europe/Kyiv`);
            // the bare `timezone` rule only knows canonical names and 422'd every Ukrainian phone.
            // PHP resolves an alias to the same rules, so the stored value stays usable as-is.
            'timezone' => ['sometimes', 'timezone:all_with_bc'],
        ];
    }
}
