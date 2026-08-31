<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Dto;

/**
 * ONE task of a plan session: an ordinary card, plus the four things that make it part of a plan
 * rather than part of a study session.
 *
 * The card is deliberately untouched — same shape, same grading, same offline check as every other
 * card the app deals. Everything a plan adds rides BESIDE it, which is what keeps «не менять сами
 * тренажёры и их UI-контракты» true: a client that ignores this envelope still plays the session
 * correctly, it just cannot say «слово из дня 1, ступень B, 3 из 4».
 */
final readonly class PlanSessionTaskView
{
    /**
     * @param  list<string>  $knobsApplied  the level's knobs this card actually honoured
     * @param  list<string>  $knobsIgnored  knobs configured for this trainer that nothing reads yet
     *                                      ({@see \App\Modules\Learning\Domain\Service\PlanKnobSupport})
     */
    public function __construct(
        public SessionCardView $card,
        /** `a`/`b`/`c`, or null in a soft session, which has no stages. */
        public ?string $stage,
        /** Which step of the stage's checklist this is, 1-based. 0 in a soft session. */
        public int $ordinal,
        /** How many steps the stage has for THIS word — «3 из 4». */
        public int $ofSteps,
        /** «слово из дня k». Null for a due term that belongs to no day of this plan. */
        public ?int $fromDayIndex,
        /** This word is on gentler knobs for the rest of its stage — three misses in a row. */
        public bool $softened,
        /** `new` | `plan_review` | `other_review` | `soft` — which bucket this task came from. */
        public string $source,
        /** What the speaking card shows at this stage; null on every other trainer. */
        public ?string $speakingForm,
        public array $knobsApplied,
        public array $knobsIgnored,
        /**
         * THE SENTENCE THE GAP IS CUT FROM, on a `cloze` card and nowhere else.
         *
         * The day's own frame when the card has one — «I worked on ___», with the slot already
         * marked — and the card's example otherwise, which is what every cloze outside a plan has
         * always used. Two reasons it has to be said out loud rather than left to the client:
         *
         *   a LINE's example is the turn AROUND it, so a gap cut there would blank a word the card
         *   never taught;
         *   a WORD's day-scoped example already IS its frame filled in, so the two agree — and the
         *   frame says WHERE the hole is instead of leaving the client to find the term in the
         *   sentence and hope.
         *
         * Additive: a client that ignores it keeps cutting the gap the way it does today.
         */
        public ?string $clozeSource = null,
    ) {}
}
