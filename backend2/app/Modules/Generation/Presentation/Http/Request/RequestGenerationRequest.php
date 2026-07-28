<?php

declare(strict_types=1);

namespace App\Modules\Generation\Presentation\Http\Request;

use Illuminate\Foundation\Http\FormRequest;

final class RequestGenerationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'prompt' => ['required', 'string', 'min:2', 'max:500'],
            'levels' => ['sometimes', 'array', 'min:1'],
            'levels.*' => ['string', 'in:A1,A2,B1,B2,C1,C2'],
            'size' => ['sometimes', 'integer', 'min:8', 'max:25'],
            'source_lang' => ['sometimes', 'string', 'min:2', 'max:5'],
            'target_lang' => ['sometimes', 'string', 'min:2', 'max:5'],
        ];
    }
}
