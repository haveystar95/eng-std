<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CheckAnswerRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'word_id' => ['required', 'integer', 'exists:words,id'],
            'user_answer' => ['required', 'string', 'max:500'],
            'mode' => ['sometimes', 'in:translation,usage'],
        ];
    }
}
