<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Command;

use App\Modules\Plan\Domain\ValueObject\PlanId;

/** Ask the photo ladder again for everything still without a photo — of one plan, or of every plan that is not deleted. */
final readonly class FillMissingImages
{
    public function __construct(
        public ?PlanId $planId,
    ) {}
}
