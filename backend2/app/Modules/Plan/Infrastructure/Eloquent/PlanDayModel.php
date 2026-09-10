<?php

declare(strict_types=1);

namespace App\Modules\Plan\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $plan_id
 * @property string $user_id
 * @property int $number
 * @property string $type
 * @property string|null $scene_id
 * @property string $status
 * @property string|null $opens_on
 * @property string|null $opened_at
 * @property string|null $closed_at
 * @property int $cards_total
 * @property int $cards_done
 * @property int $minutes_spent
 * @property string|null $first_try_share
 * @property string|null $hardest_unit_kind
 * @property string|null $hardest_unit_ref
 * @property string|null $hardest_unit_text
 */
final class PlanDayModel extends Model
{
    protected $table = 'plan_days';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = ['number' => 'int', 'cards_total' => 'int', 'cards_done' => 'int', 'minutes_spent' => 'int'];
}
