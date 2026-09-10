<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Query;

use App\Modules\Shared\Domain\ValueObject\UserId;

/** Every plan of the learner that is not deleted — the live one first, then the rest, newest first. */
final readonly class ListPlans
{
    public function __construct(public UserId $actorId) {}
}
