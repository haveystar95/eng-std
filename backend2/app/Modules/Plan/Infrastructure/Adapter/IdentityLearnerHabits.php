<?php

declare(strict_types=1);

namespace App\Modules\Plan\Infrastructure\Adapter;

use App\Modules\Identity\Application\Query\GetUsualVisitTime;
use App\Modules\Identity\Application\Query\GetUsualVisitTimeHandler;
use App\Modules\Plan\Application\Port\LearnerHabits;
use App\Modules\Shared\Domain\ValueObject\UserId;

/** The usual visit time, from Identity's visit log through its Application query. */
final readonly class IdentityLearnerHabits implements LearnerHabits
{
    public function __construct(private GetUsualVisitTimeHandler $usual) {}

    public function usualVisitMinutes(UserId $user): int
    {
        return ($this->usual)(new GetUsualVisitTime($user))->minutesOfDay;
    }
}
