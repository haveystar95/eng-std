<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Command;

use App\Modules\Shared\Domain\ValueObject\UserId;

/**
 * TAKE THE PAID PLAN BACK (наряд ACC-1 §2, `php artisan access:revoke {user}`): every right of the learner still in force
 * becomes `expired`, ending now. The rows stay — when a right ended is part of it.
 */
final readonly class RevokeAccess
{
    public function __construct(public UserId $userId) {}
}
