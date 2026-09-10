<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Query;

use App\Modules\Plan\Application\Service\PlanAccess;
use App\Modules\Plan\Domain\ValueObject\PlanId;
use App\Modules\Shared\Domain\ValueObject\UserId;

/** The one fact an answer response needs beside the card: which language's voice to resolve audio for. */
final readonly class GetPlanTargetLang
{
    public function __construct(private PlanAccess $access) {}

    public function __invoke(PlanId $planId, UserId $actor): string
    {
        return $this->access->owned($planId, $actor)->targetLang()->value;
    }
}
