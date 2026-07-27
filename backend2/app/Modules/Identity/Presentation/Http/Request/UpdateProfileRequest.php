<?php

declare(strict_types=1);

namespace App\Modules\Identity\Presentation\Http\Request;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The actor can only ever edit their own profile (resolved from the bearer token).
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'native_language' => ['sometimes', 'string', 'min:2', 'max:5'],
            'target_language' => ['sometimes', 'string', 'min:2', 'max:5'],
            'cefr_level' => ['sometimes', 'string', 'in:A1,A2,B1,B2,C1,C2'],
            'daily_goal' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
