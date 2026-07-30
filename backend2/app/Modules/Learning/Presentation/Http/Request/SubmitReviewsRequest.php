<?php

declare(strict_types=1);

namespace App\Modules\Learning\Presentation\Http\Request;

use Illuminate\Foundation\Http\FormRequest;

final class SubmitReviewsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'reviews' => ['required', 'array', 'min:1', 'max:200'],
            'reviews.*.id' => ['required', 'string', 'size:26'],       // client-generated ULID
            'reviews.*.term_id' => ['required', 'string', 'size:26'],
            'reviews.*.exercise_mode' => ['required', 'string', 'in:multiple_choice,word_bank,typing,listening,cloze'],
            'reviews.*.response' => ['present', 'string'],             // raw answer; the server grades it
            'reviews.*.answered_at' => ['required', 'date'],
            'reviews.*.used_hint' => ['sometimes', 'boolean'],
            'reviews.*.is_practice' => ['sometimes', 'boolean'],
            'reviews.*.latency_ms' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'reviews.*.session_id' => ['sometimes', 'nullable', 'string', 'size:26'],
        ];
    }
}
