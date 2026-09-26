<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Command;

use App\Modules\Plan\Domain\ValueObject\PlanId;

/** The queued voice of a plan's rescue kit, in its learner's voice (наряд LANG-1b §2). */
final readonly class VoiceRescueKit
{
    public function __construct(public PlanId $planId) {}
}
