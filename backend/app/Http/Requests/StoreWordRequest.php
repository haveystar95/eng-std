<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreWordRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'term' => ['required', 'string', 'max:255'],
            'translation' => ['required', 'string', 'max:255'],
            'transcription' => ['nullable', 'string', 'max:255'],
            'example' => ['nullable', 'string'],
            'cefr_level' => ['nullable', 'in:A1,A2,B1,B2,C1,C2'],
        ];
    }
}
