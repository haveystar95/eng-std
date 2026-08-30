<?php

declare(strict_types=1);

namespace App\Modules\Learning\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $user_id
 * @property string $status
 * @property string $title
 * @property string $goal_text
 * @property string|null $goal_restated
 * @property string $target_lang
 * @property string $level
 * @property string $event_date
 * @property int $minutes_per_day
 * @property array<string, mixed>|null $outline
 * @property array<string, mixed>|null $computed
 * @property \Illuminate\Support\Carbon|null $started_at
 * @property \Illuminate\Support\Carbon|null $completed_at
 * @property array<string, mixed>|null $event_feedback
 */
final class PlanModel extends Model
{
    protected $table = 'learning_plans';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'outline' => 'array',
        'computed' => 'array',
        'event_feedback' => 'array',
        'minutes_per_day' => 'int',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];
}
