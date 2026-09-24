<?php

declare(strict_types=1);

namespace App\Modules\Plan\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $plan_id
 * @property string $user_id
 * @property int $order
 * @property string $kind
 * @property int $priority
 * @property string $title_native
 * @property string $title_target
 * @property string $teaches_native
 * @property list<string> $goals_native
 * @property string $learner_role_target
 * @property string $learner_role_native
 * @property string $partner_role_target
 * @property string $partner_role_native
 * @property string $topic_description
 * @property string $image_prompt
 * @property string|null $image_url
 * @property string|null $image_author
 * @property string|null $image_author_url
 * @property string|null $image_tone
 * @property array<string, mixed>|null $lesson_json
 * @property string $lesson_status
 * @property string|null $prompt_version_lesson
 * @property string|null $build_version
 * @property string|null $model_lesson
 * @property string|null $cost_usd_lesson
 * @property int|null $latency_ms_lesson
 * @property int|null $attempts_lesson
 * @property array<int, array<string, string>>|null $checks_json
 * @property string|null $fail_reason
 * @property string|null $build_started_at
 * @property string|null $generated_at
 * @property string|null $built_at
 * @property string|null $partner_voice_gender
 */
final class PlanSceneModel extends Model
{
    protected $table = 'plan_scenes';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'goals_native' => 'array', 'lesson_json' => 'array', 'checks_json' => 'array',
        'order' => 'int', 'priority' => 'int', 'latency_ms_lesson' => 'int', 'attempts_lesson' => 'int',
    ];
}
