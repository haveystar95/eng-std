<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Query;

use App\Modules\Shared\Domain\ValueObject\UserId;

/** A learner's access to the paid plan for the admin's page (наряд ACC-1 §2; page ADM-2) — read only. */
final readonly class GetUserAccess
{
    public function __construct(public UserId $userId) {}
}
