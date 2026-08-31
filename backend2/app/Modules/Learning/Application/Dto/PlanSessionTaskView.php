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
    /** This task is the day's own material — it counts towards «день пройден». */
    public const SECTION_DAY = 'day';

    /** Top-up from the learner's ordinary queue — it does not count towards the day. */
    public const SECTION_REVIEW = 'review';

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
        /**
         * WHICH SIDE OF THE SEAM this task is on — {@see SECTION_DAY} or {@see SECTION_REVIEW}.
         *
         * The same fact as «`fromDayIndex` is not null», named once on the server instead of being
         * re-derived by every client. It exists because a client got that derivation wrong in the
         * one way that matters: the end-of-session screen counted all sixty-three tasks as the day's
         * and announced «День 1 пройден · 21 фраза и слово» over a day of fourteen, seven of whose
         * cards belonged to another plan and another language.
         *
         * The tasks are ordered day-first, so this never alternates: every {@see SECTION_DAY} task
         * precedes every {@see SECTION_REVIEW} one, and {@see PlanSessionView::$dayTaskCount} is
         * where the change happens.
         */
        public string $section,
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
