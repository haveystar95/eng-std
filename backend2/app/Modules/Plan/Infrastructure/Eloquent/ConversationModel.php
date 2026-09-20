<?php

declare(strict_types=1);

namespace App\Modules\Plan\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $user_id
 * @property string $plan_id
 * @property string $day_id
 * @property int $day_number
 * @property string $type
 * @property string $state
 * @property list<string> $scene_ids
 * @property list<string> $checkpoints_done
 * @property int $turn_limit
 * @property bool $hints_enabled
 * @property string $cost_usd
 * @property string|null $ended_reason
 * @property string $started_at
 * @property string|null $ended_at
 */
final class ConversationModel extends Model
{
    protected $table = 'conversations';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'scene_ids' => 'array',
        'checkpoints_done' => 'array',
        'day_number' => 'int',
        'turn_limit' => 'int',
        'hints_enabled' => 'bool',
    ];
}
