<?php

declare(strict_types=1);

namespace App\Modules\Identity\Presentation\Http\Request;

use Illuminate\Foundation\Http\FormRequest;

final class RemovePushTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // only the bearer's own row can be removed; the handler scopes by owner
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'platform' => ['required', 'string', 'in:ios'],
            'token' => ['required', 'string', 'min:1', 'max:255'],
        ];
    }
}
