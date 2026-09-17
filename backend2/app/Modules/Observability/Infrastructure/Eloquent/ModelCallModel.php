<?php

declare(strict_types=1);

namespace App\Modules\Observability\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $status
 * @property string $provider
 * @property string $model
 * @property string|null $answered_model
 * @property string|null $purpose
 * @property int $estimated_tokens_in
 * @property int|null $tokens_in
 * @property int|null $cached_tokens
 * @property int|null $tokens_out
 * @property string|null $cost_usd
 * @property int|null $http_status
 * @property string|null $error
 * @property int $timeout_seconds
 * @property int|null $latency_ms
 * @property \Illuminate\Support\Carbon $started_at
 * @property \Illuminate\Support\Carbon|null $finished_at
 */
final class ModelCallModel extends Model
{
    protected $table = 'model_calls';

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'estimated_tokens_in' => 'int',
        'tokens_in' => 'int',
        'cached_tokens' => 'int',
        'tokens_out' => 'int',
        'http_status' => 'int',
        'timeout_seconds' => 'int',
        'latency_ms' => 'int',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];
}
