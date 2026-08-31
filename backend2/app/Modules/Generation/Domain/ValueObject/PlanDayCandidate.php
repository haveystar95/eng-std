<?php

declare(strict_types=1);

namespace App\Modules\Generation\Domain\ValueObject;

/** Everything {@see \App\Modules\Generation\Domain\Service\PlanDayValidator} needs, and nothing else. */
final readonly class PlanDayCandidate
{
    /**
     * @param  list<PlanDayItem>  $items   every card of the day — lines, words and connectors together
     * @param  list<string>  $goalTerms    Latin-alphabet names the learner typed. They stay
     *                                     verbatim in BOTH languages, so they are the one
     *                                     legitimate reason for foreign letters in a key.
     * @param  list<string>  $openingLines what the day's interlocutors actually say, verbatim from
     *                                     the skeleton. A line marked `speaker: role` has to be one
     *                                     of these — the conversation the learner will hold starts
     *                                     from them, and a line that is not in the day is a line
     *                                     the learner meets unprepared.
     * @param  int  $checkpointCount       how many checkpoints this day has to close
     */
    public function __construct(
        public string $supportLang,
        public string $targetLang,
        public int $termBudget,
        public int $phraseCount,
        public int $chunkCount,
        public int $wordCount,
        public int $checkpointCount,
        public array $goalTerms,
        public array $openingLines,
        public array $items,
    ) {}
}
