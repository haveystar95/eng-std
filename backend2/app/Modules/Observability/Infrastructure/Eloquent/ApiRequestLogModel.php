<?php

declare(strict_types=1);

namespace App\Modules\Observability\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $direction
 * @property string $method
 * @property string|null $host
 * @property string $path
 * @property string|null $service
 * @property int|null $status
 * @property int|null $duration_ms
 * @property string|null $user_id
 * @property int|null $request_bytes
 * @property int|null $response_bytes
 * @property array<mixed>|null $request_headers
 * @property array<mixed>|null $request_body
 * @property array<mixed>|null $response_body
 * @property string|null $error
 * @property \Illuminate\Support\Carbon $occurred_at
 * @property \Illuminate\Support\Carbon|null $created_at
 */
final class ApiRequestLogModel extends Model
{
    protected $table = 'api_request_logs';

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'status' => 'int',
        'duration_ms' => 'int',
        'request_bytes' => 'int',
        'response_bytes' => 'int',
        'request_headers' => 'array',
        'request_body' => 'array',
        'response_body' => 'array',
        'occurred_at' => 'datetime',
        'created_at' => 'datetime',
    ];
}
