<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Query;

use App\Modules\Shared\Domain\ValueObject\UserId;

/** The tab's first question: is there a live plan, and what does it show today. */
final readonly class GetCurrentPlan
{
    public function __construct(public UserId $actorId) {}
}
