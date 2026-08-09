<?php

declare(strict_types=1);

namespace App\Modules\Admin\Infrastructure\Eloquent;

use App\Modules\Shared\Domain\ValueObject\Ulid;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * A back-office operator. Thin and Laravel-native (like Identity's User): auth is the framework's
 * job, there are no domain invariants to protect. It is the `admin` guard's provider model, so a
 * Sanctum token minted here authenticates admin routes and a user token never does.
 *
 * @property string $id
 * @property string $email
 * @property string $password
 * @property string $name
 */
final class Admin extends Authenticatable
{
    use HasApiTokens;
    use HasUlids;

    protected $table = 'admins';

    protected $keyType = 'string';

    public $incrementing = false;

    /** @var list<string> */
    protected $fillable = ['email', 'password', 'name'];

    /** @var list<string> */
    protected $hidden = ['password', 'remember_token'];

    /** Uppercase Crockford ULIDs, consistent with every other id in the app. */
    public function newUniqueId(): string
    {
        return Ulid::generate();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }
}
