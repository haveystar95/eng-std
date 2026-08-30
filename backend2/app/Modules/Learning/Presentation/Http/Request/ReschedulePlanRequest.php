<?php

declare(strict_types=1);

namespace App\Modules\Learning\Presentation\Http\Request;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Everything here is optional and at least one must be present — this is a PATCH on the arithmetic,
 * and a PATCH with no field is a model call the learner did not ask for.
 */
final class ReschedulePlanRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'minutes_per_day' => ['sometimes', 'integer', 'min:5', 'max:120'],
            'event_date' => ['sometimes', 'date_format:Y-m-d'],
            'drop_day_index' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    /** @return list<\Closure(\Illuminate\Validation\Validator): void> */
    public function after(): array
    {
        return [function (\Illuminate\Validation\Validator $validator): void {
            if (! $this->hasAny(['minutes_per_day', 'event_date', 'drop_day_index'])) {
                $validator->errors()->add('minutes_per_day', 'Нужно изменить хотя бы одно: минуты, дату или состав дней.');
            }
        }];
    }
}
