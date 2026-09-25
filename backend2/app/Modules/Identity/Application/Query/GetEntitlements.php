<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Query;

use App\Modules\Shared\Domain\ValueObject\UserId;

/** Every right of the learner, in force or not (наряд ACC-1 §2) — what the admin's page lists under the access. */
final readonly class GetEntitlements
{
    public function __construct(public UserId $userId) {}
}
