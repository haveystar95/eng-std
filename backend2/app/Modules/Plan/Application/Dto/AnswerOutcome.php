<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto;

use App\Modules\Plan\Domain\Entity\DayCard;

/** The answered card, and the card dealt again at the end of the stage when the answer was a first failure. */
final readonly class AnswerOutcome
{
    public function __construct(
        public DayCard $card,
        public ?DayCard $requeued,
    ) {}
}
