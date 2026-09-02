<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\Service;

use App\Modules\Learning\Domain\Exception\EventDateInPast;
use App\Modules\Learning\Domain\ValueObject\ComputedDay;
use App\Modules\Learning\Domain\ValueObject\ComputedPlan;
use App\Modules\Learning\Domain\ValueObject\DeadlineCheck;
use App\Modules\Learning\Domain\ValueObject\PlanDayKind;
use App\Modules\Learning\Domain\ValueObject\PlanOutline;
use App\Modules\Learning\Domain\ValueObject\PlanScene;
use App\Modules\Learning\Domain\ValueObject\PlanSkill;
use DateInterval;
use DateTimeImmutable;

/**
 * A1 — THE DAYS ARE COUNTED BY THE SERVER.
 *
 * The one rule this class exists to hold. A model asked «how many days until the 12th» answers
 * plausibly, and plausible is the one thing a deadline cannot use: the plan's whole value is that
 * it lands on the day of the event, and a plan that lands a day late is not a slightly worse plan,
 * it is a plan that did not happen. So the model is asked only what it is good at — what the goal
 * is made of, who the learner will talk to, what has to be heard — and every number that touches
 * the calendar is computed here, from the event date, today, and the minutes the learner has.
 *
 * Pure and total: same inputs, same output, no clock of its own, no I/O. The «today» it works from
 * is handed in, which is also what makes «а что будет, если я начну в четверг» a test rather than a
 * conversation.
 *
 * ## The day's size is the SCENE's, and `need` is now only a sentence on a card
 *
 * `need` measures DEMAND — the sum of what P1 priced every ability at ({@see PlanSkill::$estTerms}).
 * Until v0.4 it was also a DIVISOR: days were `ceil(need / capacity)`, and the day the learner
 * opened was however much of a situation fitted into fourteen cards. Since v0.4 the number of days
 * is the number of scenes and the size of a day is {@see SceneDay::UNITS} — «≈25 единиц» — so
 * `need` and `capacity` survive as things the plan SAYS («~75 фраз и слов», «20 минут в день») and
 * decide nothing.
 *
 * That is not a loss of a check. What `need` was protecting against — a plan too big for its
 * deadline — is now checked in the unit the learner actually experiences: scenes against days.
 *
 * ## The arithmetic, in the order it happens
 *
 *   scenes    how many situations the goal is made of — {@see PlanOutline::$scenes}
 *   max_days  days from today to the event INCLUSIVE
 *   need      Σ of what every ability costs — kept for the «срок мал» card, no longer a divisor
 *   capacity  how many cards a day at this many minutes holds ({@see DayCapacity}) — reported to
 *             the preview and used by nothing else since v0.4
 *
 * The last day teaches nothing: it is the run-through. So there are `max_days − 1` days available
 * for teaching, and since v0.4 the arithmetic ends there: ONE DAY IS ONE SCENE (канон §2), so the
 * plan needs as many teaching days as it has scenes, and what does not fit is the tail.
 *
 * ## Why the packing went, and what it was doing wrong
 *
 * Until v0.4 abilities were packed into days by CAPACITY: a day held fourteen cards, a long scene
 * was split across two days and two short scenes shared one. That produced days the learner could
 * not name — half of «регистратура» and the beginning of «кабинет врача» in one sitting — and it is
 * the thing the canon replaced: «День = одна сцена (полный тариф — до двух)». A situation is the
 * unit a person prepares for, and half a situation prepares them for nothing.
 *
 * ## The plan is not allowed to be longer than {@see MAX_INTRO_DAYS} days of teaching
 *
 * Fourteen introduction days, whatever the calendar says. A goal that asks for twenty of them is
 * not a plan, it is a course, and this feature promises a dated mechanism that ends: a learner who
 * is still meeting new material on day nineteen has no room left to REVIEW any of it, and the
 * abilities of day one arrive at the event untouched since. So the abilities past the cap are
 * dropped exactly the way abilities past the deadline are — in P1's order, named in `dropped`,
 * `fits` false — and the reason is recorded separately (`drop_reason = 'cap'`), because «перенеси
 * дату» is the answer to one of them and not to the other.
 *
 * ## The step: teaching spread over the room there actually is
 *
 * `step = clamp(floor(teaching_days / intro_days), 1, 3)` calendar days between introduction days.
 * The old rule had two answers — back to back, or every other day — and a plan with a month of
 * slack used the second one, which put every teaching day inside the first week and then left
 * three weeks of silence before the event. Three is the ceiling because a word met once and then
 * left alone for four days is a word met once; the repetition planner («когда») owns everything
 * after that, and it is not this class's business.
 *
 * The step is CALENDAR days and it moves introduction days only. Stages B and C of a word land in
 * the sessions after its nights ({@see \App\Modules\Learning\Domain\Service\PlanStageLadder}),
 * not on dates this class computes — a collection lives three stages, always, and how long that
 * takes is the learner's, not the plan's.
 *
 * ## Two cases the general rule gets wrong, and both are real
 *
 * **The event is TODAY.** `max_days = 1`, so there is one day and it does both jobs: the FIRST
 * scene, plus the run-through of everything it promises. Under the old rule the whole plan was
 * compressed into it and nothing was dropped; that was possible while a day was a bag of cards, and
 * it is not while a day is a situation — two situations in one sitting is two sittings. So the
 * remaining scenes are named in `dropped` and the learner is told, rather than handed a day that
 * cannot be walked.
 *
 * **It does not fit.** Whole SCENES are taken in P1's order until the days run out, and the rest
 * are named in `dropped`. The order is load-bearing: P1 writes scenes typical-before-deep, so
 * dropping from the end drops the rare encounter rather than the one that will certainly happen.
 * Nothing is dropped silently — that is what A7 ({@see recheck()}) and the «срок мал» card are for.
 */
final class PlanScheduler
{
    /**
     * The most introduction days a plan may have, whatever the calendar offers.
     *
     * See the class docblock: past this the plan stops being a plan. It is a HARD cap and not a
     * default — the learner cannot raise it by choosing a later date, because a later date is
     * exactly the input that would push it past fourteen.
     */
    public const MAX_INTRO_DAYS = 14;

    /** The widest gap allowed between two introduction days. */
    public const MAX_STEP = 3;

    /**
     * @param  DateTimeImmutable  $eventDate  the day it happens. Time of day is ignored throughout —
     *                                        a plan is counted in days, and a UTC timestamp that is
     *                                        «tomorrow» in the learner's timezone is a bug this
     *                                        class refuses to have an opinion about: the caller
     *                                        hands in dates already resolved in the owner's zone.
     *
     * @throws EventDateInPast
     */
    public function compute(
        PlanOutline $outline,
        int $minutesPerDay,
        DateTimeImmutable $eventDate,
        DateTimeImmutable $today,
        string $supportLang = 'ru',
    ): ComputedPlan {
        $event = $this->midnight($eventDate);
        $start = $this->midnight($today);

        $maxDays = $this->daysInclusive($start, $event);
        if ($maxDays < 1) {
            throw EventDateInPast::make($event->format('Y-m-d'), $start->format('Y-m-d'));
        }

        $capacity = $this->capacityFor($minutesPerDay);
        $need = $this->sum($outline->skills());

        // ── The event is today ────────────────────────────────────────────────────────────────
        // One day, one scene, and the rest of the plan is honestly out of reach. The old rule
        // compressed the WHOLE plan into that day, which was possible while a day was a bag of
        // cards and is not while it is a situation: two situations in one sitting is two sittings.
        if ($maxDays === 1) {
            $droppedScenes = array_slice($outline->scenes, 1);
            // The parse refuses a skeleton with no scenes at all, so there is always a first one.
            $first = $outline->scenes[0];

            return new ComputedPlan(
                days: [$this->sceneDay(
                    index: 1,
                    scene: $first,
                    scheduledOn: $event,
                    finalCheckpoints: $outline->finalCheckpoints(),
                )],
                need: $need,
                capacity: $capacity,
                maxDays: 1,
                introDays: 1,
                restDays: 0,
                fits: $droppedScenes === [],
                dropped: $this->skillsOf($droppedScenes),
                step: 1,
                dropReason: $droppedScenes === [] ? null : ComputedPlan::DROP_DEADLINE,
                finalSameDay: true,
            );
        }

        // ── The general case: ONE DAY PER SCENE, in the order P1 wrote them ────────────────────
        $teachingDays = $maxDays - 1;
        // The room is bounded TWICE and the tighter bound wins: by the calendar, and by the cap on
        // how long a plan may be. Which one bit is remembered — they are different sentences on the
        // learner's card and different decisions for them.
        $daysAllowed = min($teachingDays, self::MAX_INTRO_DAYS);
        $kept = array_slice($outline->scenes, 0, $daysAllowed);
        $droppedScenes = array_slice($outline->scenes, $daysAllowed);

        $introDays = max(1, count($kept));
        $restDays = $teachingDays - $introDays;

        // Spread the teaching over the room there is, up to three days apart. `floor`, so the last
        // introduction day never lands after the event.
        $step = max(1, min(self::MAX_STEP, intdiv($teachingDays, $introDays)));

        $days = [];
        foreach ($kept as $i => $scene) {
            $days[] = $this->sceneDay(
                index: $i + 1,
                scene: $scene,
                scheduledOn: $start->add(new DateInterval('P' . ($i * $step) . 'D')),
                finalCheckpoints: null,
            );
        }

        // The final day: introduces nothing, owns no collection, and carries EVERY checkpoint of
        // the plan — assembled here from the days, never asked of the model.
        $days[] = new ComputedDay(
            index: $introDays + 1,
            kind: PlanDayKind::Final,
            title: self::finalDayTitle($supportLang),
            scheduledOn: $event,
            termBudget: 0,
            skills: [],
            checkpoints: $this->checkpointsOf($days),
            role: null,
            topics: [],
            sourceSceneIndex: null,
        );

        return new ComputedPlan(
            days: $days,
            need: $need,
            capacity: $capacity,
            maxDays: $maxDays,
            introDays: $introDays,
            restDays: $restDays,
            fits: $droppedScenes === [],
            dropped: $this->skillsOf($droppedScenes),
            step: $step,
            // The cap only gets the blame when it was the binding constraint — i.e. the calendar
            // had more days to offer and this class refused them.
            dropReason: $droppedScenes === []
                ? null
                : ($teachingDays > self::MAX_INTRO_DAYS ? ComputedPlan::DROP_CAP : ComputedPlan::DROP_DEADLINE),
            finalSameDay: false,
        );
    }

    /**
     * ONE SCENE, LAID ON A DATE — the whole of «день = одна сцена целиком» (канон §2).
     *
     * Everything the day carries comes off the scene, and the two computations that used to happen
     * here went with the packing: there is no budget to divide, because a day's size is the scene's
     * ({@see SceneDay::UNITS}), and no title to invent, because the scene has one.
     *
     * @param  list<string>|null  $finalCheckpoints  the whole plan's checkpoints, on a same-day plan
     */
    private function sceneDay(
        int $index,
        PlanScene $scene,
        DateTimeImmutable $scheduledOn,
        ?array $finalCheckpoints,
    ): ComputedDay {
        $skills = $scene->skills;

        $checkpoints = [];
        foreach ($skills as $skill) {
            if ($skill->checkpoint !== '') {
                $checkpoints[] = $skill->checkpoint;
            }
        }

        return new ComputedDay(
            index: $index,
            kind: PlanDayKind::Intro,
            title: $scene->title !== '' ? $scene->title : ($skills[0]->outcome ?? 'День плана'),
            scheduledOn: $scheduledOn,
            termBudget: SceneDay::units(),
            skills: $skills,
            checkpoints: $finalCheckpoints ?? $checkpoints,
            role: $scene->role(),
            topics: $scene->topics(),
            sourceSceneIndex: $scene->index,
            intro: $scene->intro,
            openingLines: $scene->openingLines,
            entities: $scene->entities,
        );
    }

    /**
     * Every ability of the scenes that did NOT fit — what the «срок мал» card is built from.
     *
     * Whole scenes, never a skill from the middle of one: a day is a situation, and half a
     * situation is a lesson about nothing. The order is P1's, so what goes is the tail — the rare
     * encounter rather than the one that will certainly happen.
     *
     * @param  list<PlanScene>  $scenes
     * @return list<PlanSkill>
     */
    private function skillsOf(array $scenes): array
    {
        $out = [];
        foreach ($scenes as $scene) {
            foreach ($scene->skills as $skill) {
                $out[] = $skill;
            }
        }

        return $out;
    }

    /**
     * A7 — the same question asked again, mid-plan.
     *
     * A learner falls behind, or moves the event nearer. Nothing is re-cut: this reports whether
     * what is LEFT still fits in what is left, and which abilities would be the ones to go. The
     * decision is the learner's, on a card built from this answer.
     *
     * @param  list<ComputedDay>  $remainingIntroDays  the intro days not yet finished, in order
     *
     * @throws EventDateInPast
     */
    public function recheck(
        array $remainingIntroDays,
        int $minutesPerDay,
        DateTimeImmutable $eventDate,
        DateTimeImmutable $today,
    ): DeadlineCheck {
        $event = $this->midnight($eventDate);
        $start = $this->midnight($today);

        $daysRemaining = $this->daysInclusive($start, $event);
        if ($daysRemaining < 1) {
            throw EventDateInPast::make($event->format('Y-m-d'), $start->format('Y-m-d'));
        }

        $capacity = $this->capacityFor($minutesPerDay);
        // The final day teaches nothing, so it is not room — unless the event is today, in which
        // case the one day left is both and the learner is going to have to compress. The cap
        // bounds this too: a plan that fell behind cannot buy itself twenty introduction days by
        // having a distant event, for the same reason it could not buy them at the start.
        $introDaysRemaining = min(self::MAX_INTRO_DAYS, max(1, $daysRemaining - 1));

        $skills = [];
        foreach ($remainingIntroDays as $day) {
            foreach ($day->skills as $skill) {
                $skills[] = $skill;
            }
        }
        $needRemaining = $this->sum($skills);

        // A DAY IS A SCENE, so what is left is counted in DAYS and not in cards. The old reading
        // divided the remaining abilities by a day's capacity, which was the right question while a
        // day was a bag of fourteen; a learner who is three scenes behind with two days left has a
        // scene at risk whether those scenes are large or small, and compressing two situations
        // into one sitting is not something the calendar can buy.
        $atRisk = [];
        foreach (array_slice($remainingIntroDays, $introDaysRemaining) as $day) {
            foreach ($day->skills as $skill) {
                $atRisk[] = $skill;
            }
        }

        return new DeadlineCheck(
            needRemaining: $needRemaining,
            capacity: $capacity,
            daysRemaining: $daysRemaining,
            introDaysRemaining: $introDaysRemaining,
            deadlineTight: $atRisk !== [],
            atRisk: $atRisk,
        );
    }

    /**
     * How many terms one day holds at this many minutes — {@see DayCapacity}, and nowhere else.
     *
     * Kept as a method here because callers ask the SCHEDULER this question and the table is an
     * implementation of the answer, not the answer's address. One table, three readers: this, the
     * preview (the same `compute()`), and the day brief's three counts.
     */
    public function capacityFor(int $minutesPerDay): int
    {
        return DayCapacity::forMinutes($minutesPerDay);
    }

    /**
     * The rehearsal day's NAME, in the learner's own language.
     *
     * A constant and no longer a model field. P1 v0.2 has no `final_day` at all: the day that
     * introduces nothing is entirely the server's, its checkpoint list is assembled from the
     * abilities ({@see PlanOutline::finalCheckpoints()}), and a title is the last thing worth
     * paying a model for. Unknown support languages get the English one rather than a Russian
     * sentence they cannot read.
     */
    public static function finalDayTitle(string $supportLang): string
    {
        return match (mb_strtolower(substr($supportLang, 0, 2))) {
            'ru' => 'Прогон перед событием',
            default => 'Rehearsal before the event',
        };
    }

    /**
     * @param  list<ComputedDay>  $days
     * @return list<string>
     */
    private function checkpointsOf(array $days): array
    {
        $out = [];
        foreach ($days as $day) {
            foreach ($day->checkpoints as $checkpoint) {
                $out[] = $checkpoint;
            }
        }

        return $out;
    }

    /** @param list<PlanSkill> $skills */
    private function sum(array $skills): int
    {
        $total = 0;
        foreach ($skills as $skill) {
            $total += $skill->estTerms;
        }

        return $total;
    }

    /** Whole days from `$from` to `$to`, both ends counted. Same day → 1. */
    private function daysInclusive(DateTimeImmutable $from, DateTimeImmutable $to): int
    {
        $diff = $from->diff($to);
        $days = (int) $diff->days;

        return $diff->invert === 1 && $days > 0 ? -$days : $days + 1;
    }

    private function midnight(DateTimeImmutable $moment): DateTimeImmutable
    {
        return $moment->setTime(0, 0, 0);
    }
}
