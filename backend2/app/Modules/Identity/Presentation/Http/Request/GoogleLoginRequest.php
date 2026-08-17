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
            'timezone' => ['sometimes', 'timezone'], // device IANA zone → seeds the profile for F19 due rounding
        ];
    }
}
