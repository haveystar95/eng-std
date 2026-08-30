<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Query;

use App\Modules\Shared\Domain\ValueObject\UserId;

/** The words and phrases of one day of one plan, each with the stage it stands on. */
final readonly class GetPlanDayTerms
{
    public function __construct(
        public UserId $actorId,
        public string $planId,
        public int $dayIndex,
    ) {}
}
