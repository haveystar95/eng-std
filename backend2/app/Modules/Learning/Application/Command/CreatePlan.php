<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Command;

use App\Modules\Shared\Domain\ValueObject\UserId;

/** Start a plan as a DRAFT: the goal and the calendar, nothing generated, nothing enrolled. */
final readonly class CreatePlan
{
    public function __construct(
        public UserId $actorId,
        public string $goalText,
        public string $targetLang,
        public string $level,
        public string $eventDate,      // Y-m-d, in the learner's own timezone
        public int $minutesPerDay,
    ) {}
}
