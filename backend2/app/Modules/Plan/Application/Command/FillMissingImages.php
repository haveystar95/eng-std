<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Command;

use App\Modules\Plan\Domain\ValueObject\PlanId;

/**
 * Ask the photo ladder again for everything still without a photo — of one plan, or of every plan that
 * is not deleted. [$requeryOldPhotos]: also re-ask the photos the ladder before DAY-UI-3 gave — the words
 * and chunks whose photo the bare word found (no description of their own), and the ones repeating a
 * picture their day already shows — and write the new answer over the old photo.
 */
final readonly class FillMissingImages
{
    public function __construct(
        public ?PlanId $planId,
        public bool $requeryOldPhotos = false,
    ) {}
}
