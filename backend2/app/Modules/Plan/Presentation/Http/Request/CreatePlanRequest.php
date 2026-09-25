<?php

declare(strict_types=1);

namespace App\Modules\Plan\Presentation\Http\Request;

use App\Modules\Plan\Domain\Service\PlanCalendar;
use App\Modules\Plan\Domain\ValueObject\PlanLevel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CreatePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'goal_text' => ['required', 'string', 'min:1', 'max:1000'],
            // THE SHAPE of a code, not the list (наряд LANG-1 §7). Whether a plan may be built in it depends on
            // the learner's native too, which only the command reads (the profile's) — so a well-formed code
            // outside the plan's lists reaches it and is refused there as the pair it makes: 422
            // `language_pair_invalid`, `meta {target, native}`. Malformed input stays Laravel's 422. Two
            // letters and not «2–5»: every language the catalogue names is an ISO 639-1 pair of letters
            // (`LanguageCatalogTest`), and the `LanguageCode` the command carries refuses a longer run of
            // letters — «xyz» would pass a looser rule here and fail as a 500 there.
            'target_lang' => ['required', 'string', 'regex:/^[a-z]{2}$/'],
            'level' => ['required', Rule::enum(PlanLevel::class)],
            'days_total' => ['required', 'integer', 'min:'.PlanCalendar::MIN_DAYS, 'max:'.PlanCalendar::MAX_DAYS],
            'event_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
        ];
    }
}
