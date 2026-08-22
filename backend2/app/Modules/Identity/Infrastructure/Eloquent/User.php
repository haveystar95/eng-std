<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Eloquent;

use App\Modules\Shared\Domain\ValueObject\Ulid;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * The Sanctum-backed user. Identity is a deliberately thin, Laravel-native module: the
 * user stays an Eloquent model rather than a domain aggregate (there are no invariants to
 * protect — auth is the framework's job). Its id is a ULID so it matches the `UserId`
 * value object every other module keys on.
 *
 * @property string $id
 * @property string $name
 * @property string|null $email
 * @property string|null $google_id
 * @property string|null $avatar
 * @property bool $is_qa
 */
class User extends Authenticatable
{
    use HasApiTokens;
    /** @use HasFactory<UserFactory> */
    use HasFactory;
    use HasUlids;
    use Notifiable;

    public mixed $profile;
    protected $keyType = 'string';

    public $incrementing = false;

    /** @var list<string> */
    protected $fillable = ['name', 'email', 'google_id', 'avatar', 'is_qa'];

    /** @var list<string> */
    protected $hidden = ['password', 'remember_token'];

    /** Uppercase Crockford ULIDs, consistent with the Shared UserId every module keys on. */
    public function newUniqueId(): string
    {
        return Ulid::generate();
    }

    /** @return HasOne<Profile, $this> */
    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            // The QA mark. Cast so `$user->is_qa` is a bool on every driver — the guards that read
            // it are `if (! $user->is_qa)`, and a string '0' would open every one of them.
            'is_qa' => 'boolean',
        ];
    }

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }
}
