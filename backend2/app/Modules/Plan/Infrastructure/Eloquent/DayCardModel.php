<?php

declare(strict_types=1);

namespace App\Modules\Plan\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $day_id
 * @property string $user_id
 * @property string $stage
 * @property int $position
 * @property string $kind
 * @property array<string, mixed> $payload
 * @property string $source
 * @property string|null $source_day_id
 * @property string $unit_kind
 * @property string $unit_ref
 * @property string|null $retry_of
 * @property string|null $result
 * @property int $attempts
 * @property string|null $answered_at
 * @property bool $returns
 */
final class DayCardModel extends Model
{
    protected $table = 'day_cards';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = ['payload' => 'array', 'position' => 'int', 'attempts' => 'int', 'returns' => 'bool'];
}
