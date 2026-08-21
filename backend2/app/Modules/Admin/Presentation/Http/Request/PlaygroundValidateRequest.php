<?php

declare(strict_types=1);

namespace App\Modules\Admin\Presentation\Http\Request;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Rows to run past the real validator, plus the reference to judge them against.
 *
 * `error_type` is optional and nothing else is: a row without a sentence, a span or a correction is
 * not a distractor at all, and refusing it here is clearer than letting the validator report three
 * different gates for one missing field. An absent `error_type` gets a valid one substituted, and
 * the response says so per row.
 *
 * The reference is not required by the rules either: with neither a term nor a manual pair, every
 * row comes back «нет закреплённого примера», which is the honest verdict for that input and a
 * better teacher than a validation error.
 */
final class PlaygroundValidateRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.sentence' => ['required', 'string', 'max:1000'],
            'items.*.error_span' => ['required', 'string', 'max:500'],
            'items.*.correction' => ['required', 'string', 'max:500'],
            'items.*.error_type' => ['sometimes', 'nullable', 'string', 'max:32'],
            'term_id' => ['sometimes', 'nullable', 'string', 'max:26'],
            'manual' => ['sometimes', 'array'],
            'manual.term_text' => ['sometimes', 'nullable', 'string', 'max:200'],
            'manual.example_text' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }
}
