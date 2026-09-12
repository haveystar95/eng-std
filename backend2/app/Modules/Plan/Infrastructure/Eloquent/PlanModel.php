<?php

declare(strict_types=1);

namespace App\Modules\Plan\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $user_id
 * @property string $goal_text
 * @property string $target_lang
 * @property string $native_lang
 * @property string $level
 * @property int $days_total
 * @property int $days_requested
 * @property string|null $event_date
 * @property string $status
 * @property string|null $title_native
 * @property string|null $title_target
 * @property string|null $event_native
 * @property string|null $until_phrase_native
 * @property string|null $overdue_native
 * @property string|null $cover_image_prompt
 * @property string|null $learner_role_target
 * @property string|null $learner_role_native
 * @property string|null $cover_image_url
 * @property string|null $cover_image_author
 * @property string|null $cover_image_author_url
 * @property string|null $cover_image_tone
 * @property string|null $prompt_version_plan
 * @property string|null $build_version
 * @property string|null $model_plan
 * @property string|null $cost_usd_plan
 * @property int|null $latency_ms_plan
 * @property int|null $attempts_plan
 * @property array<int, array<string, string>>|null $checks_json
 * @property string|null $unclear_reason
 * @property string|null $fail_reason
 * @property string|null $build_started_at
 * @property string|null $collection_id
 * @property string|null $started_at
 * @property string|null $finished_at
 * @property string $created_at
 */
final class PlanModel extends Model
{
    protected $table = 'plans';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = ['checks_json' => 'array', 'days_total' => 'int', 'days_requested' => 'int', 'latency_ms_plan' => 'int', 'attempts_plan' => 'int'];

    /** @return HasMany<PlanSceneModel, $this> */
    public function scenes(): HasMany
    {
        return $this->hasMany(PlanSceneModel::class, 'plan_id')->orderBy('order');
    }

    /** @return HasMany<PlanDayModel, $this> */
    public function days(): HasMany
    {
        return $this->hasMany(PlanDayModel::class, 'plan_id')->orderBy('number');
    }
}
