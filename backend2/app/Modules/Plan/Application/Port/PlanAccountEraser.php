<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Port;

use App\Modules\Shared\Domain\ValueObject\UserId;

/** Account deletion: every plan of the learner, with its scenes, days, cards, terms and audio rows. */
interface PlanAccountEraser
{
    public function eraseFor(UserId $userId): void;
}
