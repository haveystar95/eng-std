<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'native_language' => ['sometimes', 'string', 'max:10'],
            'target_language' => ['sometimes', 'string', 'max:10'],
            'cefr_level' => ['sometimes', 'in:A1,A2,B1,B2,C1,C2'],
            'daily_goal' => ['sometimes', 'integer', 'min:1', 'max:200'],
        ];
    }
}
