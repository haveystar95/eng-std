<?php

declare(strict_types=1);

namespace App\Modules\Learning\Presentation\Http\Request;

use App\Modules\Learning\Domain\ValueObject\PlanLevel;
use App\Modules\Shared\Domain\Service\LanguageRoles;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The three things a plan cannot be built without: the goal, the language, the level.
 *
 * `minutes_per_day` has a default rather than being required — 20 is the figure the capacity table
 * is anchored on and the one the sandbox ran, and a learner who has not thought about it should
 * not be made to.
 *
 * ## The date left the list (кадр V4·04б)
 *
 * `event_date` is now nullable and that is a product decision, not a loosening: «Без даты» is a
 * whole shape of plan — nothing compressed toward a deadline, nothing «срок мал», the rehearsal at
 * the end instead of the eve. `nullable` and not «absent»: a client that omits the key entirely and
 * one that sends `null` mean the same thing, and neither may be read as «сегодня».
 *
 * ## `listening` is the raw answers, never the verdict
 *
 * The optional step of the entry sends what the learner tapped, line by line. The balance
 * («упор на понимание» / «упор на говорение») is DERIVED on the server
 * ({@see \App\Modules\Learning\Domain\ValueObject\ListeningDiagnostics::emphasis()}) and is not
 * accepted from the wire: a client that could send it could send one that disagrees with its own
 * rows, and the plan would then be built against a verdict the learner never saw.
 */
final class CreatePlanRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // Long enough for a real goal («иду к врачу, болит спина, надо объяснить и понять
            // назначение») and short enough that the field is not a diary.
            'goal_text' => ['required', 'string', 'min:5', 'max:500'],
            'target_lang' => ['required', 'string', 'in:' . implode(',', LanguageRoles::taught())],
            'level' => ['required', 'string', 'in:' . implode(',', array_column(PlanLevel::cases(), 'value'))],
            'event_date' => ['present', 'nullable', 'date_format:Y-m-d'],
            // 5 minutes is the floor the capacity table can still answer for; 120 is a long
            // evening. Neither is a product limit, both are guards against a typo becoming a
            // 600-term day.
            'minutes_per_day' => ['sometimes', 'integer', 'min:5', 'max:120'],
            // Three lines at most — the step plays three and the server caps the answer at three.
            // A longer list is a client that made the step up, and it is refused rather than
            // trimmed: a diagnostics nobody heard is worse than none.
            'listening' => ['sometimes', 'array', 'max:3'],
            'listening.*.text' => ['required', 'string', 'max:300'],
            'listening.*.translation' => ['sometimes', 'nullable', 'string', 'max:300'],
            'listening.*.place' => ['sometimes', 'nullable', 'string', 'max:120'],
            'listening.*.understood' => ['required', 'boolean'],
        ];
    }
}
