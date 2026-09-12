<?php

declare(strict_types=1);

namespace App\Modules\Identity\Presentation\Http\Request;

use Illuminate\Foundation\Http\FormRequest;

final class VisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * `timezone` is accepted and validated but not stored: the usual visit time is read in
     * `profiles.timezone`, which the client keeps current through `PUT /profile`.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'timezone' => ['sometimes', 'nullable', 'timezone:all_with_bc'],
        ];
    }
}
