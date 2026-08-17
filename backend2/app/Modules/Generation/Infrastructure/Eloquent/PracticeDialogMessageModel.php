<?php

declare(strict_types=1);

namespace App\Modules\Generation\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $dialog_id
 * @property string $role
 * @property string $text
 * @property int $ts
 * @property \Illuminate\Support\Carbon $created_at
 */
final class PracticeDialogMessageModel extends Model
{
    protected $table = 'practice_dialog_messages';

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'ts' => 'int',
        'created_at' => 'datetime',
    ];
}
