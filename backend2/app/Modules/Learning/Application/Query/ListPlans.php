<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Query;

use App\Modules\Shared\Domain\ValueObject\UserId;

/** The learner's plans, newest first — the План tab's finished plan and its archive. */
final readonly class ListPlans
{
    public function __construct(public UserId $actorId, public int $limit = 20) {}
}
