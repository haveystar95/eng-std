<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\ValueObject;

/** THE LEARNER'S ACCESS ON THE WIRE (наряд ACC-1 §2, `GET /auth/me` → `access.plan`): the free plan or the paid one. */
enum AccessPlan: string
{
    case Free = 'free';
    case Premium = 'premium';
}
