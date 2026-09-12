<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Port;

use App\Modules\Shared\Domain\ValueObject\UserId;

/** When the learner usually comes — read from Identity's visit log. */
interface LearnerHabits
{
    /** The reminder hour as minutes after local midnight in the learner's zone: a whole hour, ≥ 08:00, 19:00 without visits. */
    public function usualVisitMinutes(UserId $user): int;
}
