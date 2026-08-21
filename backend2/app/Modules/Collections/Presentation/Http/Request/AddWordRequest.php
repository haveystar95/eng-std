<?php

declare(strict_types=1);

namespace App\Modules\Collections\Presentation\Http\Request;

use Illuminate\Foundation\Http\FormRequest;

final class AddWordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Two ways in, and exactly one of them per request.
     *
     * `term_id` puts an EXISTING term into the folder — the word is already in the database (found
     * by search, or sitting in another folder) and nothing is created. `text` creates or dedups a
     * term from what the learner typed. `required_without` on both is what stops a request that
     * names neither; a request that names both is refused too, because "which one wins" is a
     * question with no good answer and a silent precedence rule is how the wrong term gets added.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'term_id' => ['required_without:text', 'prohibits:text', 'string', 'ulid'],
            'text' => ['required_without:term_id', 'string', 'min:1', 'max:200'],
            // Optional: when omitted, the term is enriched (translation/transcription/example/photo).
            'translation' => ['sometimes', 'nullable', 'string', 'max:500'],
            'type' => ['sometimes', 'string', 'in:word,phrase'],
        ];
    }
}
