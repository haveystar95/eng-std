<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Command;

use App\Modules\Plan\Domain\ValueObject\PlanId;

/**
 * Ask the photo ladder again for everything still without a photo — of one plan, or of every plan that
 * is not deleted. [$requeryBareWords]: also re-ask the words and chunks whose photo the bare word
 * found before DAY-UI-3 (no description of their own) and write the new answer over the old photo.
 */
final readonly class FillMissingImages
{
    public function __construct(
        public ?PlanId $planId,
        public bool $requeryBareWords = false,
    ) {}
}
