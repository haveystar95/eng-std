<?php

declare(strict_types=1);

namespace App\Modules\Generation\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $user_id
 * @property string $collection_id
 * @property string $status
 * @property array<string, mixed> $lesson_json
 * @property \Illuminate\Support\Carbon $expires_at
 * @property int|null $tokens_in
 * @property int|null $tokens_out
 * @property string|null $cost_usd
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon|null $finished_at
 * @property string|null $summary
 */
final class PracticeDialogModel extends Model
{
    protected $table = 'practice_dialogs';

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'lesson_json' => 'array',
        'tokens_in' => 'int',
        'tokens_out' => 'int',
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
        'finished_at' => 'datetime',
    ];
}
