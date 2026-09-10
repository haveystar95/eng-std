<?php

declare(strict_types=1);

namespace App\Modules\Plan\Presentation\Http\Request;

use App\Modules\Plan\Domain\Service\PlanCalendar;
use Illuminate\Foundation\Http\FormRequest;

/** «Перенести дату» / more or fewer days. `event_date: null` clears the date; a missing key leaves it. */
final class ReschedulePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'event_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'days_total' => ['sometimes', 'integer', 'min:'.PlanCalendar::MIN_DAYS, 'max:'.PlanCalendar::MAX_DAYS],
        ];
    }
}
