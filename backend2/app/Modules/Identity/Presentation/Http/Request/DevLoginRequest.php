<?php

declare(strict_types=1);

namespace App\Modules\Identity\Presentation\Http\Request;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The dev sign-in body: an email, plus the same optional device name and timezone the Google login
 * takes. No password field exists — there is nothing to check, and a field that looked like a
 * credential would suggest this door is safer than it is.
 */
final class DevLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'device_name' => ['sometimes', 'string', 'max:100'],
            // Same rule as the Google login: `all_with_bc`, because a simulator reports the same
            // legacy aliases a phone does (`Europe/Kiev`).
            'timezone' => ['sometimes', 'timezone:all_with_bc'],
        ];
    }
}
