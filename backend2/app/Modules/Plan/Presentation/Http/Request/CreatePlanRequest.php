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
            'target_lang' => ['required', 'string', 'regex:/^[a-z]{2,5}$/'],
            'level' => ['required', Rule::enum(PlanLevel::class)],
            'days_total' => ['required', 'integer', 'min:'.PlanCalendar::MIN_DAYS, 'max:'.PlanCalendar::MAX_DAYS],
            'event_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
        ];
    }
}
