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
        ];
    }
}
