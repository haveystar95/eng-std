<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Dto;

/**
 * A whole plan, structure and all.
 *
 * The naряд's answer to «что отдаёт GET /plans/active»: everything, in one read. The screens for
 * this do not exist yet (1c), and a partial payload designed against imagined screens is how an API
 * ends up needing a second version the week the screens arrive.
 */
final readonly class PlanView
{
    /**
     * @param  list<PlanDayView>  $days
     * @param  array<string, mixed>|null  $computed  the server's arithmetic, verbatim
     * @param  list<string>  $constraints
     * @param  list<string>  $goalTerms
     * @param  list<string>  $entities  proper names of the scenario, gathered from every scene.
     *         Plain names since P1 v0.4: the gender and number a v0.2 skeleton carried existed for
     *         one Russian agreement check, and the check retired with the field.
     */
    public function __construct(
        public string $id,
        public string $status,
        public string $title,
        public string $goalText,
        public ?string $goalRestated,
        public string $supportLang,
        public string $targetLang,
        public string $level,
        /** «Без даты» is NULL, and it is a different plan rather than a distant one. */
        public ?string $eventDate,
        public int $minutesPerDay,
        public ?string $startedAt,
        public ?string $completedAt,
        public array $days,
        public ?array $computed,
        public array $entities,
        public array $constraints,
        public array $goalTerms,
        /**
         * How ready the learner is, 0…1 — THE WHOLE FORMULA, with one half still worth zero.
         *
         * `0.6 × (чек-пойнты, подтверждённые в разговоре без подсказки / все) + 0.4 × (термины на
         * ступени C / все)`. The conversation is CONV-1 and does not exist, so the first half is a
         * literal ZERO — not an estimate and not a proxy. The second half is real from PLAN-1b: it
         * counts the words that have reached the last stage of the plan's own ladder.
         *
         * The number can therefore only ever GROW as the feature lands, never be revised downwards,
         * which is the property that makes shipping half a formula safe. A learner who has taken
         * every word to stage C and never had a conversation reads 0.4, and that is honest: they
         * know the material and have not yet said any of it to anybody.
         */
        public float $readiness,
        /**
         * The day the learner is ON — the first introduction day not yet passed, or the final day's
         * index when they all are. Derived from the review log, never stored.
         */
        public int $focusDayIndex,
        /** The next introduction day after the focus, or null when there is none left. */
        public ?int $nextDayIndex,
        /**
         * Whole days from today to the event. 0 = today, negative = the event has passed, NULL =
         * the plan has no date and there is nothing to count down to.
         */
        public ?int $daysToEvent,
        /**
         * A7: what is LEFT no longer fits in the days that are left ({@see PlanScheduler::recheck()}).
         * Nothing is cut on the strength of this — it is the «срок мал» card's input, and the
         * decision is the learner's.
         */
        public bool $deadlineTight,
        /**
         * «ТЫ УЖЕ МОЖЕШЬ» — every checkpoint of the plan with its status, ready for the screen.
         *
         * Shipped now, with every `hit` false, because the STRUCTURE is what the screen is built
         * against and the conversation that flips them is a later наряд. A screen that had to wait
         * for CONV-1 to know its own shape is a screen that gets rewritten twice.
         *
         * @var list<array{text: string, day_index: int, hit: bool}>
         */
        public array $canAlready,
        /**
         * «На приёме сказал 5 из 6» — which checkpoints the learner ticked after the event.
         *
         * Indexes into {@see $canAlready}. NULL means they were never asked (the plan is still
         * running, or the question went unanswered); an empty list means they were asked and used
         * none of it. The finished-plan screen needs to tell those two apart.
         *
         * @var list<int>|null
         */
        public ?array $eventFeedback = null,
        /**
         * WHAT THE WORK LOOKS LIKE TODAY — the count `readiness` cannot express while half its
         * formula does not exist.
         *
         * `readiness` counts the cards that reached their LAST stage (a line after B, a word after
         * C), which is right and, for the first two days of a plan, always zero: a day that closes
         * stage A moves every one of its cards ONTO stage B, not through it. The owner walked
         * fifty-six cards on 02.09 and the card said «0% готовность к событию».
         *
         * So the plan card shows this instead until the canonical formula arrives with
         * P2-v0.4/SIT-1: how many cards the plan has written, and how many of them have closed
         * stage A — the thing a sitting actually moves. Nothing here feeds `readiness`; it is a
         * second, plainer answer to «что я сделал», and the two are computed from the same
         * standings.
         *
         * @var array{total: int, stage_a_closed: int}
         */
        public array $stageCensus = ['total' => 0, 'stage_a_closed' => 0],
        /**
         * ЗРЕЛОСТЬ КАЖДОЙ СЦЕНЫ — три слова канона и последний прогон (наряд SCENE-RUN, Ч.3).
         *
         * `maturity` — `met` | `applying` | `speaking` ({@see SceneMaturity}), то, что человек
         * читает на экране плана словами. `ready` — «C + скорость» (`docs/plan-model.md` §4), число
         * для API и админки; на экранах плана процентов нет и это правило не двигается.
         *
         * `run` — итог ПОСЛЕДНЕГО прогона этой сцены, или null, если её ещё не прогоняли: «Прошёл
         * сам N из M · сразу K» на кадре DL·10 и в итоге дня — это конкретный прогон, а не
         * накопленный счёт, и путать их значило бы показывать вчерашний результат как сегодняшний.
         *
         * @var list<array{day_index: int, maturity: string, ready: bool, run: array{total: int, said: int, said_fast: int, skipped: int, rescued: int}|null}>
         */
        public array $scenes = [],
    ) {}
}
