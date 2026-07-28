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
            'reviews.*.grade' => ['required', 'string', 'in:again,hard,good,easy'],
            'reviews.*.answered_at' => ['required', 'date'],
            'reviews.*.latency_ms' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ];
    }
}
