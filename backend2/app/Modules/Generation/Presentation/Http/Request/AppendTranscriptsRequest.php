<?php

declare(strict_types=1);

namespace App\Modules\Generation\Presentation\Http\Request;

use Illuminate\Foundation\Http\FormRequest;

final class AppendTranscriptsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'events' => ['required', 'array', 'min:1', 'max:200'],
            'events.*.role' => ['required', 'string', 'in:user,assistant'],
            'events.*.text' => ['required', 'string', 'max:4000'],
            'events.*.ts' => ['required', 'integer', 'min:0'],
        ];
    }
}
