<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AnswerReviewRequest extends FormRequest
{
    public function rules(): array
    {
        return ['rating' => ['required', 'integer', 'min:1', 'max:4']];
    }
}
