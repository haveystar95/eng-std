<?php

declare(strict_types=1);

namespace App\Modules\Generation\Presentation\Http\Request;

use Illuminate\Foundation\Http\FormRequest;

final class AddSearchResultRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * `lookup_id` XOR `term_id`, and `collection_id` optional (omitted = «Сохранённые»).
     *
     * `prohibits` on both sides rather than a precedence rule: the two ids are both ULIDs, so a
     * handler that quietly preferred one would save the wrong word without anything looking odd.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'lookup_id' => ['required_without:term_id', 'prohibits:term_id', 'string', 'ulid'],
            'term_id' => ['required_without:lookup_id', 'string', 'ulid'],
            'collection_id' => ['sometimes', 'nullable', 'string', 'ulid'],
            // The translation the learner confirmed in the translator before building this card.
            // Sent again on the SAVE and not only on the lookup, deliberately: the lookup may have
            // been a free cache hit written by somebody else's call, and the confirmation still has
            // to reach the term. See AddSearchResultHandler.
            'fixed_translation' => ['sometimes', 'nullable', 'string', 'max:255'],
            // Which of the two acts this is: «Сохранить» (shelf only, false) or «Учить сразу»
            // (shelf AND queue, true). ABSENT means true — the door's old behaviour, kept for the
            // build already on a phone, which has one button and no field to send.
            'enroll' => ['sometimes', 'boolean'],
        ];
    }
}
