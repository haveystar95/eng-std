<?php

declare(strict_types=1);

namespace App\Modules\Learning\Presentation\Http\Request;

use App\Modules\Learning\Domain\ValueObject\PlanLevel;
use App\Modules\Shared\Domain\Service\LanguageRoles;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The four things a plan cannot be built without: the goal, the language, the level, the date.
 *
 * `minutes_per_day` has a default rather than being required — 20 is the figure the capacity table
 * is anchored on and the one the sandbox ran, and a learner who has not thought about it should
 * not be made to.
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
            'event_date' => ['required', 'date_format:Y-m-d'],
            // 5 minutes is the floor the capacity table can still answer for; 120 is a long
            // evening. Neither is a product limit, both are guards against a typo becoming a
            // 600-term day.
            'minutes_per_day' => ['sometimes', 'integer', 'min:5', 'max:120'],
        ];
    }
}
