<?php

declare(strict_types=1);

namespace App\Modules\Admin\Presentation\Http\Request;

use Illuminate\Foundation\Http\FormRequest;

final class ChangeTierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'tier' => ['required', 'string', 'in:free,premium'],
        ];
    }
}
