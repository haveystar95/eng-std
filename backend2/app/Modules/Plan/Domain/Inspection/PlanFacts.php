<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Inspection;

/** Everything the checks of «Что не так» read — gathered once by the application, read by every check. */
final readonly class PlanFacts
{
    /**
     * @param  list<DayFact>  $days
     * @param  list<LineFact>  $lines
     * @param  list<CardSoundFact>  $cardSounds
     * @param  list<TalkFact>  $talks
     * @param  list<CallFact>  $calls
     */
    public function __construct(
        public array $days = [],
        public array $lines = [],
        public array $cardSounds = [],
        public array $talks = [],
        public array $calls = [],
    ) {}
}
