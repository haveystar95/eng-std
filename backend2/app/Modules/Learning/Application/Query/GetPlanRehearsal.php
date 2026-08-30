<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Query;

use App\Modules\Shared\Domain\ValueObject\UserId;

/** The plan's phrases, for the three minutes before the event. */
final readonly class GetPlanRehearsal
{
    public function __construct(public UserId $actorId, public string $planId) {}
}
