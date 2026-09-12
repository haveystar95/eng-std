<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Port;

use App\Modules\Shared\Domain\ValueObject\UserId;

/** When the learner usually comes — read from Identity's visit log. */
interface LearnerHabits
{
    /** Minutes after local midnight in the learner's zone, a multiple of 15; 19:00 without visits. */
    public function usualVisitMinutes(UserId $user): int;
}
