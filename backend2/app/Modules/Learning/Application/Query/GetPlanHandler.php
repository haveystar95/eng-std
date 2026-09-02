<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Query;

use App\Modules\Learning\Application\Dto\PlanDayView;
use App\Modules\Learning\Application\Dto\PlanProgressView;
use App\Modules\Learning\Application\Dto\PlanView;
use App\Modules\Learning\Application\Service\PlanProgress;
use App\Modules\Learning\Domain\Entity\LearningPlan;
use App\Modules\Learning\Domain\Entity\PlanDay;
use App\Modules\Learning\Domain\Exception\EventDateInPast;
use App\Modules\Learning\Domain\Repository\PlanDayRepository;
use App\Modules\Learning\Domain\Repository\PlanRepository;
use App\Modules\Learning\Domain\Service\PlanScheduler;
use App\Modules\Learning\Domain\ValueObject\ComputedDay;
use App\Modules\Learning\Domain\ValueObject\PlanDayKind;
use App\Modules\Learning\Domain\ValueObject\PlanId;
use App\Modules\Learning\Domain\ValueObject\PlanSkill;
use App\Modules\Learning\Domain\ValueObject\PlanStage;
use DateTimeImmutable;

/**
 * The whole plan in one read — structure, days, arithmetic and readiness.
 *
 * Everything, deliberately. The screens land in 1c and a payload trimmed against imagined screens
 * is how an API needs a second version the week the real ones arrive.
 */
final readonly class GetPlanHandler
{
    public function __construct(
        private PlanRepository $plans,
        private PlanDayRepository $days,
        private PlanProgress $progress,
        private PlanScheduler $scheduler = new PlanScheduler(),
    ) {}

    public function __invoke(GetPlan $query): ?PlanView
    {
        $plan = $query->planId !== null
            ? $this->plans->findById(PlanId::fromString($query->planId))
            : $this->plans->findActiveFor($query->actorId);

        if ($plan === null || ! $plan->userId()->equals($query->actorId)) {
            return null;
        }

        return $this->view($plan);
    }

    private function view(LearningPlan $plan): PlanView
    {
        $outline = $plan->parsedOutline();
        // The PLAN's language, not the account's — see LearningPlan::$supportLang.
        $support = $plan->supportLang();

        $planDays = $this->days->listForPlan($plan->id());
        $days = array_map(fn (PlanDay $day): PlanDayView => $this->dayView($day), $planDays);

        // The same computation the plan SESSION runs on — one answer to «where is this learner»,
        // shared, because a screen drawn against one focus and a session built against another is
        // the kind of disagreement that reads as a client bug for a week.
        $progress = $this->progress->forPlan($plan, $planDays);
        $today = new DateTimeImmutable($progress->today . ' 00:00:00');

        return new PlanView(
            id: $plan->id()->value,
            status: $plan->status()->value,
            title: $plan->title(),
            goalText: $plan->goalText(),
            goalRestated: $plan->goalRestated(),
            supportLang: $support->value,
            targetLang: $plan->targetLang()->value,
            level: $plan->level()->value,
            eventDate: $plan->eventDate()->format('Y-m-d'),
            minutesPerDay: $plan->minutesPerDay(),
            startedAt: $plan->startedAt()?->format(DATE_ATOM),
            completedAt: $plan->completedAt()?->format(DATE_ATOM),
            days: $days,
            computed: $plan->computed(),
            entities: $outline === null ? [] : $outline->entities,
            constraints: $outline === null ? [] : $outline->constraints,
            goalTerms: $outline === null ? [] : $outline->goalTerms,
            readiness: $this->readinessOf($progress, $days),
            focusDayIndex: $progress->focusDayIndex,
            nextDayIndex: $this->nextDayIndex($planDays, $progress->focusDayIndex),
            daysToEvent: (int) $today->diff($plan->eventDate()->setTime(0, 0))->format('%r%a'),
            deadlineTight: $this->deadlineTight($plan, $planDays, $progress, $today),
            canAlready: $this->canAlready($days),
            eventFeedback: $plan->eventFeedback(),
            stageCensus: self::stageCensusOf($progress),
        );
    }

    /**
     * HOW MANY CARDS THE PLAN HAS WRITTEN, AND HOW MANY HAVE CLOSED STAGE A.
     *
     * The plain answer to «что я сделал», beside the one `readiness` gives. Readiness counts the
     * cards that reached their LAST stage, so a day that closes stage A moves its cards ONTO
     * stage B and the percentage stays at zero — true, and unreadable to somebody who has just
     * walked fifty-six cards ({@see PlanView::$stageCensus}).
     *
     * «Closed stage A» is: standing past A, or standing on A with every trainer of it ticked. The
     * second half matters on the day itself — the stage closes in the sitting and the card only
     * LEAVES A after the night.
     *
     * Counted off the same standings `readiness` is, so the two can never tell different stories
     * about the same card.
     *
     * @return array{total: int, stage_a_closed: int}
     */
    private static function stageCensusOf(PlanProgressView $progress): array
    {
        $closed = 0;
        $standings = $progress->allStandings();
        foreach ($standings as $standing) {
            if ($standing->stage !== PlanStage::A || $standing->stageComplete) {
                $closed++;
            }
        }

        return ['total' => count($standings), 'stage_a_closed' => $closed];
    }

    /**
     * «Ты уже можешь» — every checkpoint of every INTRODUCTION day, in day order, each with its
     * status.
     *
     * The final day is skipped: its checkpoints are the plan's own, assembled by the server from
     * the teaching days ({@see PlanScheduler}), so including it would list every line twice.
     *
     * `hit` is false for all of them and will stay false until CONV-1: a checkpoint is confirmed by
     * being SAID in the conversation without a prompt, and there is no conversation yet. It is here
     * as `false` rather than absent because the screen is built against this shape.
     *
     * @param  list<PlanDayView>  $days
     * @return list<array{text: string, day_index: int, hit: bool}>
     */
    private function canAlready(array $days): array
    {
        $out = [];
        foreach ($days as $day) {
            if ($day->kind !== PlanDayKind::Intro->value) {
                continue;
            }
            foreach ($day->checkpoints as $checkpoint) {
                $out[] = ['text' => $checkpoint, 'day_index' => $day->index, 'hit' => false];
            }
        }

        return $out;
    }

    /** @param list<PlanDay> $days */
    private function nextDayIndex(array $days, int $focus): ?int
    {
        foreach ($days as $day) {
            if ($day->kind() === PlanDayKind::Intro && $day->dayIndex() > $focus) {
                return $day->dayIndex();
            }
        }

        return null;
    }

    /**
     * A7, asked on every read of the plan.
     *
     * The abilities of the days NOT yet passed, re-checked against the days that are left. Nothing
     * is cut — this is the input to the «срок мал» card, and the decision is the learner's.
     *
     * @param  list<PlanDay>  $days
     */
    private function deadlineTight(LearningPlan $plan, array $days, PlanProgressView $progress, DateTimeImmutable $today): bool
    {
        $computed = $plan->computed();
        if ($computed === null || $plan->eventDate()->setTime(0, 0) < $today) {
            // A draft has nothing to be behind on, and a plan whose event has passed cannot be made
            // tighter by saying so.
            return false;
        }

        $remaining = [];
        foreach ($days as $day) {
            if ($day->kind() !== PlanDayKind::Intro) {
                continue;
            }
            if ($progress->days[$day->dayIndex()]->passed ?? false) {
                continue;
            }
            $remaining[] = $day;
        }
        if ($remaining === []) {
            return false;
        }

        try {
            return $this->scheduler->recheck(
                remainingIntroDays: array_map($this->computedDayOf(...), $remaining),
                minutesPerDay: $plan->minutesPerDay(),
                eventDate: $plan->eventDate(),
                today: $today,
            )->deadlineTight;
        } catch (EventDateInPast) {
            return false;
        }
    }

    /**
     * A stored day row, read back as the {@see ComputedDay} A7 needs.
     *
     * Only the fields `recheck()` touches are rebuilt — the abilities and their prices. The rest is
     * given honest filler rather than re-derived: A7 sums `est_terms` and looks at nothing else, and
     * inventing a plausible title here would be inventing data.
     */
    private function computedDayOf(PlanDay $day): ComputedDay
    {
        $skills = [];
        foreach ($day->skills() as $position => $skill) {
            $skills[] = new PlanSkill(
                id: is_string($skill['id'] ?? null) ? $skill['id'] : '',
                outcome: is_string($skill['outcome'] ?? null) ? $skill['outcome'] : '',
                checkpoint: is_string($skill['checkpoint'] ?? null) ? $skill['checkpoint'] : '',
                estTerms: is_int($skill['est_terms'] ?? null) ? $skill['est_terms'] : 1,
                sceneIndex: is_int($skill['scene_index'] ?? null) ? $skill['scene_index'] : $day->dayIndex(),
                skillIndex: (int) $position,
                position: is_int($skill['position'] ?? null) ? $skill['position'] : (int) $position,
            );
        }

        return new ComputedDay(
            index: $day->dayIndex(),
            kind: $day->kind(),
            title: $day->title(),
            scheduledOn: $day->scheduledOn() ?? new DateTimeImmutable(),
            termBudget: 0,
            skills: $skills,
            checkpoints: [],
            role: null,
            topics: [],
            sourceSceneIndex: null,
        );
    }

    /**
     * The scene's вводка out of the stored day brief.
     *
     * @param  array<string, mixed>  $brief
     */
    private static function introOf(array $brief): string
    {
        $scene = is_array($brief['scene'] ?? null) ? $brief['scene'] : [];
        $intro = $scene['intro'] ?? ($brief['intro'] ?? null);

        return is_string($intro) ? trim($intro) : '';
    }

    private function dayView(PlanDay $day): PlanDayView
    {
        $brief = $day->roleBrief() ?? [];

        /** @var list<string> $checkpoints */
        $checkpoints = is_array($brief['checkpoints'] ?? null)
            ? array_values(array_filter($brief['checkpoints'], static fn (mixed $c): bool => is_string($c)))
            : [];
        /** @var list<string> $topics */
        $topics = is_array($brief['topics'] ?? null)
            ? array_values(array_filter($brief['topics'], static fn (mixed $t): bool => is_string($t)))
            : [];

        $outcomes = [];
        foreach ($day->skills() as $skill) {
            $outcome = $skill['outcome'] ?? null;
            if (is_string($outcome) && $outcome !== '') {
                $outcomes[] = $outcome;
            }
        }

        return new PlanDayView(
            id: $day->id()->value,
            index: $day->dayIndex(),
            kind: $day->kind()->value,
            title: $day->title(),
            scheduledOn: $day->scheduledOn()?->format('Y-m-d'),
            collectionId: $day->collectionId()?->value,
            status: $day->status()->value,
            generationAttempts: $day->generationAttempts(),
            failReason: $day->failReason(),
            failCode: $day->failCode(),
            termBudget: is_int($brief['term_budget'] ?? null) ? $brief['term_budget'] : 0,
            outcomes: $outcomes,
            checkpoints: $checkpoints,
            topics: $topics,
            role: is_array($brief['role'] ?? null) ? $brief['role'] : null,
            // Read off the stored scene, with the older top-level key as the fallback: a day
            // scheduled before v0.4 has neither, and an empty вводка is a day without one rather
            // than a plan that cannot be read.
            intro: self::introOf($brief),
        );
    }

    /**
     * READINESS — the whole formula, with the conversation half still worth a literal zero.
     *
     *     0.6 × (чек-пойнты, подтверждённые в разговоре без подсказки / все)
     *   + 0.4 × (термины на ПОСЛЕДНЕЙ своей ступени / все)
     *
     * The first term is ZERO and not an estimate: `plan_conversations` has no writer until CONV-1,
     * so nothing has been confirmed out loud and pretending otherwise would put «70% готов» on a
     * plan whose conversations have never run. The second term is real from PLAN-1b — it counts the
     * cards that have reached the last stage of the plan's own ladder.
     *
     * «Last» depends on what the card IS, and that is a v0.2 correction rather than a widening: a
     * spoken line has no stage C at all, so counting `stage === C` would have held the percentage
     * down for ever with cards standing on a rung that does not exist for them. A line is ready
     * after B, a word and a connector after C ({@see PlanTermStanding::$ready}).
     *
     * The number can therefore only GROW as the feature lands. That is the property that makes
     * shipping half a formula safe, and it is the reason the weights are the FINAL ones rather than
     * renormalised over the half that exists: renormalising would make today's 0.4 read as 1.0 and
     * every later release would have to take it back.
     *
     * ## THE DENOMINATOR IS THE WHOLE PLAN, from day one (Д-31)
     *
     * It used to be «the standings that exist», and standings exist only for days that have been
     * WRITTEN. A plan writes one day at a time, so the denominator grew every time the learner
     * finished a day: the live run read 23 % after day 1, 13 % after day 2 and 8 % after day 3. The
     * learner did the work and watched their readiness for the appointment fall.
     *
     * So the denominator is what the plan set out to teach — every day's `term_budget`, written into
     * the skeleton before the first card existed — and it does not move as days arrive. `max()`
     * against what a day actually holds is the honest guard for a day that came back bigger than its
     * budget; it is the only thing that can still grow the denominator, and it grows it by what was
     * really added rather than by what was merely revealed.
     *
     * The canonical formula (C + speed) is not this: it arrives with P2-v0.4/SIT-1. What is fixed
     * here is only the monotonicity.
     *
     * @param  list<PlanDayView>  $days
     */
    private function readinessOf(PlanProgressView $progress, array $days): float
    {
        $standings = $progress->allStandings();
        if ($standings === []) {
            return 0.0;
        }

        $planned = 0;
        foreach ($days as $day) {
            if ($day->kind !== PlanDayKind::Intro->value) {
                // The final day introduces nothing — its cards are the teaching days' own, and
                // counting them twice would hold the percentage down for ever.
                continue;
            }
            $written = $progress->days[$day->index] ?? null;
            $planned += max($day->termBudget, $written === null ? 0 : count($written->termIds));
        }

        $atLast = 0;
        foreach ($standings as $standing) {
            if ($standing->ready) {
                $atLast++;
            }
        }

        // A plan whose skeleton carries no budgets at all (written before `term_budget` existed)
        // falls back to what it can see, which is exactly the reading it had before this change.
        $planned = max($planned, count($standings));

        return round(0.4 * ($atLast / $planned), 4);
    }
}
