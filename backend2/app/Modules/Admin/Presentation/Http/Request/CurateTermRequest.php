<?php

declare(strict_types=1);

namespace App\Modules\Admin\Presentation\Http\Request;

use Illuminate\Foundation\Http\FormRequest;

/** Term edit: every field optional, at least one required — a PATCH with nothing in it is a bug. */
final class CurateTermRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route is behind auth:admin
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'text' => ['sometimes', 'string', 'min:1', 'max:200'],
            'translation' => ['sometimes', 'string', 'min:1', 'max:200'],
            'ipa' => ['sometimes', 'nullable', 'string', 'max:200'],
            // Editing an example needs both halves: which one, and what it now says.
            'example_id' => ['required_with:example_sentence', 'string', 'size:26'],
            'example_sentence' => ['required_with:example_id', 'string', 'min:1', 'max:500'],
            'example_translation' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator(mixed $validator): void
    {
        $validator->after(function (mixed $v): void {
            $touched = array_intersect_key(
                $this->all(),
                array_flip(['text', 'translation', 'ipa', 'example_sentence']),
            );
            if ($touched === []) {
                $v->errors()->add('text', 'Nothing to update.');
            }
        });
    }
}
