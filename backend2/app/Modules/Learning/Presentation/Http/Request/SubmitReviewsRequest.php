<?php

declare(strict_types=1);

namespace App\Modules\Learning\Presentation\Http\Request;

use App\Modules\Learning\Domain\ValueObject\ExerciseMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            // A batch may be answers only, intros only, or both — an intro-only first session is
            // the normal shape of a brand-new collection, so neither list is required on its own.
            'reviews' => ['sometimes', 'array', 'max:200'],
            'reviews.*.id' => ['required', 'string', 'size:26'],       // client-generated ULID
            'reviews.*.term_id' => ['required', 'string', 'size:26'],
            // Derived from the enum, not retyped. This list was a hand-written string until
            // `description_match` arrived and failed validation on a card the server itself had
            // dealt — the same drift the openapi enum and the Dart enum have each had once. Only
            // GRADED modes: an `intro` produces no answer and rides the `exposures` list instead,
            // which is what the filter (rather than a shorter literal) says out loud.
            'reviews.*.exercise_mode' => ['required', 'string', Rule::in(array_map(
                static fn (ExerciseMode $mode): string => $mode->value,
                array_filter(ExerciseMode::cases(), static fn (ExerciseMode $mode): bool => $mode->isGraded()),
            ))],
            // Raw answer; the server grades it. NULLABLE: a «не помню» / blank answer legitimately
            // has no text and the client may send null — the controller coalesces it to '' (an empty
            // answer grades as a miss). Rejecting null 422'd and silently lost the review (F21).
            'reviews.*.response' => ['present', 'nullable', 'string'],
            'reviews.*.answered_at' => ['required', 'date'],           // reference-only (device clock)
            'reviews.*.client_seq' => ['required', 'integer', 'min:0'], // per-user monotonic; orders the fold
            'reviews.*.used_hint' => ['sometimes', 'boolean'],
            'reviews.*.is_practice' => ['sometimes', 'boolean'],
            'reviews.*.latency_ms' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'reviews.*.session_id' => ['sometimes', 'nullable', 'string', 'size:26'],
            // Which rung the card was dealt at. 0 is absent on purpose: rung 0 is the intro, which
            // produces no answer and therefore cannot arrive here — it belongs in `exposures`.
            'reviews.*.ladder_step' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:5'],

            // Intro cards: shown, not answered. No id, because the PAIR is the identity — the write
            // is idempotent on (user, term), so a re-uploaded batch needs no client ULID to be safe.
            'exposures' => ['sometimes', 'array', 'max:200'],
            'exposures.*.term_id' => ['required', 'string', 'size:26'],
            'exposures.*.shown_at' => ['required', 'date'],
            'exposures.*.session_id' => ['sometimes', 'nullable', 'string', 'size:26'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'reviews.array' => 'The reviews field must be an array.',
        ];
    }

    /** A batch that carries neither answers nor intros has nothing to apply. */
    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function (\Illuminate\Validation\Validator $validator): void {
            if ($this->input('reviews', []) === [] && $this->input('exposures', []) === []) {
                $validator->errors()->add('reviews', 'A batch must carry at least one review or exposure.');
            }
        });
    }
}
