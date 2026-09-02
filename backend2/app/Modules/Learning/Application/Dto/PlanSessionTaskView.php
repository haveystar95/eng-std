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
     * THE WARM-UP — the plan's five rescue phrases, dealt before the day, every day (канон §5).
     *
     * A section of its own rather than «the day's own material», because it is neither today's
     * lesson nor a revision of an earlier one: it is the same five cards on day 1 and on day 9, and
     * the count the day screen shows («N из N») must not move because the kit came back.
     */
    public const SECTION_WARMUP = 'warmup';

    /** {@see $origin} — the card came out of another plan of this learner's. */
    public const ORIGIN_PLAN = 'plan';

    /** {@see $origin} — the card came out of an ordinary collection. */
    public const ORIGIN_COLLECTION = 'collection';

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
        /**
         * WHERE A REVIEW CARD CAME FROM — «Отпуск в Италии» — or null on the day's own material.
         *
         * A card of the top-up is a word the learner met somewhere else, and dropped into the
         * middle of a plan's lesson with no explanation it reads as part of today. It was: the day
         * that started this said «Привет, я Алекс, и я работаю бэкенд-разработчиком» in a lesson
         * about a holiday, and the learner did not recognise their own word.
         *
         * `kind` says which sentence to build — `plan` is «из плана: …», `collection` is a folder —
         * and the client writes it, because the wording is the client's and there are two
         * languages of it.
         *
         * @var array{kind: string, title: string}|null
         */
        public ?array $origin = null,
        /**
         * WHOSE LINE THIS IS — `learner`, `role`, or null on anything that is not a plan line.
         *
         * The card has to say it out loud. A `role` line is the interlocutor's turn, dealt only for
         * recognition ({@see \App\Modules\Learning\Application\Service\PlanStandings}), and a
         * recognition card that does not say so is indistinguishable from one the learner is
         * expected to produce: the live run showed «Hello. What seems to be the problem with your
         * child?» as an ordinary card to learn (Д-8).
         *
         * Additive, and the client owns the wording — «Собеседник:» or the role's own name from the
         * day's skeleton — for the same reason `origin` does.
         */
        public ?string $speaker = null,
        /**
         * `line` | `word` | `chunk` — what this card DOES in its day, or null outside a plan.
         *
         * The day contract has carried it since v0.2; the SESSION did not, so the summary screen
         * counted words in the card's text instead and a connector came out «фраза»: «3 слова ·
         * 11 фраз» over a day of 4 word + 2 chunk + 8 line (Д-5). One fact, one field, both
         * contracts.
         */
        public ?string $kind = null,
        /**
         * WHICH SHELF OF THE SCENE — `hear` | `say` | `ask` | `words` | `chunks` | `numbers` |
         * `rescue`, or null outside a plan.
         *
         * The caption a seam is drawn with, named by the server because the shelf is a fact about
         * the day and the wording is the client's, in two languages ({@see $origin} for the same
         * split). `kind` cannot stand in for it: «Ты ответишь» and «Ты спросишь» are both `line`.
         */
        public ?string $shelf = null,
        /**
         * `speak` | `understand` — the ladder this card climbs (канон §3), or null outside a plan.
         *
         * The client decides nothing with it. It is on the wire so a card the server will only ever
         * ask the learner to RECOGNISE is not drawn as one they are expected to produce, which is
         * the whole of Д-8 seen from the other side.
         */
        public ?string $tier = null,
    ) {}
}
