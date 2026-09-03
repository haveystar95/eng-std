<?php

declare(strict_types=1);

namespace App\Modules\Learning\Presentation\Http\Request;

use App\Modules\Learning\Domain\ValueObject\PlanLevel;
use App\Modules\Shared\Domain\Service\LanguageRoles;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The facts the warm-up needs — and no plan among them.
 *
 * `target_lang` is the one that decides WHICH of the two answers comes back: without it the call is
 * the goal step asking for continuations, with it the full warm-up. The goal and the level are
 * required either way — continuations for «иду» would be as useless as lines for it.
 *
 * Same floor on the goal as {@see CreatePlanRequest}, on purpose: the step is offered after the
 * level, by which point the learner has already passed that floor once, and a second, looser rule
 * here would let a goal through that the plan itself would then refuse.
 */
final class ListenWarmupRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'goal_text' => ['required', 'string', 'min:5', 'max:500'],
            // OPTIONAL, and its absence is a question rather than an omission: «продолжи мою
            // цель» is asked before the language is chosen. Present-but-nonsense is still refused.
            'target_lang' => ['sometimes', 'nullable', 'string', 'in:' . implode(',', LanguageRoles::taught())],
            'level' => ['required', 'string', 'in:' . implode(',', array_column(PlanLevel::cases(), 'value'))],
        ];
    }
}
