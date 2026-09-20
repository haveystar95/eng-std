<?php

declare(strict_types=1);

namespace App\Modules\Plan\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $conversation_id
 * @property string $user_id
 * @property int $turn_index
 * @property string $kind
 * @property string $speaker
 * @property string|null $text_target
 * @property string|null $text_native
 * @property string|null $audio_path
 * @property string|null $audio_format
 * @property int|null $audio_duration_ms
 * @property string|null $audio_voice_key
 * @property int|null $audio_characters
 * @property int|null $audio_credits
 * @property string|null $audio_cost_usd
 * @property string|null $audio_request_id
 * @property bool|null $understood
 * @property list<string> $phrases_used
 * @property bool|null $off_topic
 * @property string|null $checkpoint_done
 * @property string|null $hint_native
 * @property string|null $model
 * @property string|null $prompt_version
 * @property int|null $tokens_in
 * @property int|null $tokens_out
 * @property string $model_cost_usd
 * @property string $speech_cost_usd
 * @property string $cost_usd
 * @property int|null $model_latency_ms
 * @property int|null $speech_latency_ms
 * @property int|null $latency_ms
 * @property string $created_at
 */
final class ConversationTurnModel extends Model
{
    protected $table = 'conversation_turns';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    public $timestamps = false;

    protected $casts = [
        'phrases_used' => 'array',
        'turn_index' => 'int',
        'understood' => 'bool',
        'off_topic' => 'bool',
        'audio_duration_ms' => 'int',
        'audio_characters' => 'int',
        'audio_credits' => 'int',
        'tokens_in' => 'int',
        'tokens_out' => 'int',
        'model_latency_ms' => 'int',
        'speech_latency_ms' => 'int',
        'latency_ms' => 'int',
    ];
}
