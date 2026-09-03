<?php

declare(strict_types=1);

namespace App\Modules\Learning\Presentation\Http\Request;

use App\Modules\Learning\Domain\ValueObject\PlanLevel;
use App\Modules\Shared\Domain\Service\LanguageRoles;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The three facts the listening warm-up needs — and no plan among them.
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
            'target_lang' => ['required', 'string', 'in:' . implode(',', LanguageRoles::taught())],
            'level' => ['required', 'string', 'in:' . implode(',', array_column(PlanLevel::cases(), 'value'))],
        ];
    }
}
