<?php

declare(strict_types=1);

namespace App\Modules\Learning\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $plan_id
 * @property int $day_index
 * @property string $kind
 * @property string|null $collection_id
 * @property string $title
 * @property string|null $outcome_text
 * @property list<array<string, mixed>>|null $skills
 * @property array<string, mixed>|null $role_brief
 * @property string|null $scheduled_on
 * @property string $status
 * @property int $generation_attempts
 * @property int $repair_calls
 * @property string|null $fail_code
 * @property string|null $fail_reason
 * @property list<string>|null $generation_violations
 */
final class PlanDayModel extends Model
{
    protected $table = 'learning_plan_days';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'skills' => 'array',
        'role_brief' => 'array',
        'generation_violations' => 'array',
        'day_index' => 'int',
        'generation_attempts' => 'int',
        'repair_calls' => 'int',
    ];
}
