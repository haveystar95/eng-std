<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GenerateCollectionRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'topic' => ['required', 'string', 'max:255'],
            'levels' => ['required', 'array', 'min:1'],
            'levels.*' => ['in:A1,A2,B1,B2,C1,C2'],
            'size' => ['required', 'integer', 'min:5', 'max:40'],
        ];
    }
}
