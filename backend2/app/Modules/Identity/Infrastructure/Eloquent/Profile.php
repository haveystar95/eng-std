<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Eloquent;

use App\Modules\Shared\Domain\ValueObject\Ulid;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Learning preferences for a user. One row per user (unique user_id).
 *
 * @property string $id
 * @property string $user_id
 * @property string $native_language
 * @property string $target_language
 * @property string $cefr_level
 * @property int $daily_goal
 * @property string $tier
 * @property string|null $timezone
 * @property \Illuminate\Support\Carbon|null $onboarded_at
 */
final class Profile extends Model
{
    use HasUlids;

    protected $keyType = 'string';

    public $incrementing = false;

    /** @var list<string> */
    protected $fillable = ['user_id', 'native_language', 'target_language', 'cefr_level', 'daily_goal', 'timezone', 'onboarded_at'];

    /** @var array<string, string> */
    protected $casts = ['daily_goal' => 'int', 'onboarded_at' => 'datetime'];

    public function newUniqueId(): string
    {
        return Ulid::generate();
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
