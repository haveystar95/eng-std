<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Query;

use App\Modules\Shared\Domain\ValueObject\UserId;

/** One plan by id, or — with `$planId` null — whichever plan is currently holding this learner. */
final readonly class GetPlan
{
    public function __construct(public UserId $actorId, public ?string $planId = null) {}
}
