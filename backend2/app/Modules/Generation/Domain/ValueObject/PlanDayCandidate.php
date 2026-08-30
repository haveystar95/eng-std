<?php

declare(strict_types=1);

namespace App\Modules\Generation\Domain\ValueObject;

/** Everything {@see \App\Modules\Generation\Domain\Service\PlanDayValidator} needs, and nothing else. */
final readonly class PlanDayCandidate
{
    /**
     * @param  list<PlanDayItem>  $items      every card of the day, replies and substitutions together
     * @param  list<string>  $goalTerms       Latin-alphabet names the learner typed. They stay
     *                                        verbatim in BOTH languages, so they are the one
     *                                        legitimate reason for foreign letters in a key.
     * @param  int  $checkpointCount          how many checkpoints this day has to close
     */
    public function __construct(
        public string $supportLang,
        public string $targetLang,
        public int $termBudget,
        public int $checkpointCount,
        public array $goalTerms,
        public array $items,
    ) {}
}
