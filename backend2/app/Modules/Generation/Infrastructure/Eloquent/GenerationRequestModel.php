<?php

declare(strict_types=1);

namespace App\Modules\Generation\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $user_id
 * @property string $prompt
 * @property string $normalized_prompt
 * @property string $source_lang
 * @property string $target_lang
 * @property list<string> $levels
 * @property int $size
 * @property string $prompt_version
 * @property string $status
 * @property string|null $model
 * @property int|null $tokens_in
 * @property int|null $tokens_out
 * @property string|null $cost_usd
 * @property string|null $collection_id
 * @property string|null $error
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon|null $finished_at
 */
final class GenerationRequestModel extends Model
{
    protected $table = 'generation_requests';

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'levels' => 'array',
        'size' => 'int',
        'tokens_in' => 'int',
        'tokens_out' => 'int',
        'created_at' => 'datetime',
        'finished_at' => 'datetime',
    ];
}
