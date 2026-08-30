<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\Service;

use App\Modules\Learning\Domain\Exception\EventDateInPast;
use App\Modules\Learning\Domain\ValueObject\ComputedDay;
use App\Modules\Learning\Domain\ValueObject\ComputedPlan;
use App\Modules\Learning\Domain\ValueObject\DaySpacing;
use App\Modules\Learning\Domain\ValueObject\DeadlineCheck;
use App\Modules\Learning\Domain\ValueObject\PlanDayKind;
use App\Modules\Learning\Domain\ValueObject\PlanOutline;
use App\Modules\Learning\Domain\ValueObject\PlanRole;
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
 * ## The day's budget is CAPACITY, not the sum of what landed on it
 *
 * `need` measures DEMAND — what P1 thinks the abilities cost — and it is what decides how many days
 * there are and what does not fit. What each day then ASKS FOR is `capacity`: the number of cards
 * that fit in the minutes the learner has, exactly. Those are two different questions and letting
 * the second be answered by the first is what produced days of 10 and 18 terms — a figure P1 chose
 * out of a band, handed to a validator that counts cards, on a day whose length the learner had
 * already fixed by choosing 20 minutes.
 *
 * So the model is given a NUMBER and never a band, the validator counts against that same number,
 * and a day that comes back with 8 or 11 cards is wrong rather than «within tolerance». The last
 * day of a plan may carry fewer abilities than the others and still asks for a full day's cards:
 * more material per ability is what a day with room looks like, and asking for less would leave
 * the learner short for a reason no one chose.
 *
 * ## The arithmetic, in the order it happens
 *
 *   need      Σ of what every ability costs — {@see PlanOutline::skills()}
 *   capacity  how many terms fit in ONE day at this many minutes (the table below)
 *   max_days  days from today to the event INCLUSIVE
 *
 * The last day teaches nothing: it is practice plus the conversation that runs the whole plan. So
 * there are `max_days − 1` days available for teaching, and the room the plan actually has is
 * `capacity × (max_days − 1)`. Everything else follows from comparing `need` with that number.
 *
 * ## Two cases the general rule gets wrong, and both are real
 *
 * **The event is TODAY.** `max_days = 1`, so the general rule would offer zero teaching days and
 * drop the entire plan. What the learner wants is obvious and the code says it out loud: one day
 * that does both jobs, everything compressed into it, nothing dropped. `fits` still reports
 * honestly whether it all fitted — a compressed day that is over capacity is a real fact and the
 * card says so — but nothing is CUT, because there is no later day to cut it in favour of.
 *
 * **It does not fit.** Abilities are taken in P1's order until the room runs out, and the rest are
 * named in `dropped`. The order is load-bearing: P1 writes days in DEPENDENCY order, simple before
 * complex, so dropping from the end drops what leans on the rest rather than what the rest leans
 * on. Nothing is dropped silently — that is what A7 ({@see recheck()}) and the «срок мал» card are
 * for.
 */
final class PlanScheduler
{
    /**
     * Terms per day, by minutes per day. Three measured points and a straight line between them.
     *
     * The same three numbers P1 is told about ({@see plan_outline.v0.1.md} «Term budget per
     * introduction day»), and they have to be the same numbers or the two halves of the plan
     * disagree about what a day holds: the prompt would write an 18-term day the scheduler thinks
     * holds 16, and the difference would surface as «не влезает» on a plan nobody changed.
     *
     * The prompt states a BAND (40 minutes → 16–18) and this states the number at the bottom of it.
     * That asymmetry is deliberate and is the one place the two are allowed to differ: the prompt
     * is being asked to aim, the scheduler is deciding, and a scheduler that assumed the top of
     * the band would promise room that only exists if the model is generous.
     */
    private const CAPACITY_ANCHORS = [10 => 5, 20 => 9, 40 => 16];

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
    ): ComputedPlan {
        $event = $this->midnight($eventDate);
        $start = $this->midnight($today);

        $maxDays = $this->daysInclusive($start, $event);
        if ($maxDays < 1) {
            throw EventDateInPast::make($event->format('Y-m-d'), $start->format('Y-m-d'));
        }

        $capacity = $this->capacityFor($minutesPerDay);
        $skills = $outline->skills();
        $need = $this->sum($skills);

        // ── The event is today ────────────────────────────────────────────────────────────────
        // One day, both jobs, everything compressed. `fits` still tells the truth about whether it
        // all fitted; nothing is dropped, because there is no later day to drop it in favour of.
        if ($maxDays === 1) {
            return new ComputedPlan(
                days: [$this->introDay(
                    index: 1,
                    // INTRO, even though it is also the last day, and the kind is what decides
                    // whether the day owns a collection and gets generated. A same-day plan's one
                    // day TEACHES; calling it `final` would describe the conversation correctly
                    // and stop the material from ever being written. That the final conversation
                    // happens on the same day is said by `finalSameDay`, which is where it belongs.
                    kind: PlanDayKind::Intro,
                    scheduledOn: $event,
                    skills: $skills,
                    outline: $outline,
                    finalCheckpoints: $outline->finalCheckpoints(),
                    budget: $capacity,
                )],
                need: $need,
                capacity: $capacity,
                maxDays: 1,
                introDays: 1,
                restDays: 0,
                fits: $need <= $capacity,
                dropped: [],
                spacing: DaySpacing::Daily,
                finalSameDay: true,
            );
        }

        // ── The general case ──────────────────────────────────────────────────────────────────
        $teachingDays = $maxDays - 1;
        $room = $capacity * $teachingDays;

        [$kept, $dropped] = $this->fitInOrder($skills, $room);
        $keptNeed = $this->sum($kept);
        $introDays = min($teachingDays, max(1, (int) ceil($keptNeed / $capacity)));
        $restDays = $teachingDays - $introDays;

        // Room to breathe: a free day between teaching days is what turns two days into two
        // memories instead of one blur. Only when the slack is at least as large as the teaching.
        $spacing = $restDays >= $introDays ? DaySpacing::EveryOtherDay : DaySpacing::Daily;
        $step = $spacing === DaySpacing::EveryOtherDay ? 2 : 1;

        $buckets = $this->packIntoDays($kept, $capacity, $introDays);

        $days = [];
        foreach ($buckets as $i => $bucket) {
            $days[] = $this->introDay(
                index: $i + 1,
                kind: PlanDayKind::Intro,
                scheduledOn: $start->add(new DateInterval('P' . ($i * $step) . 'D')),
                skills: $bucket,
                outline: $outline,
                finalCheckpoints: null,
                budget: $capacity,
            );
        }

        // The final day: introduces nothing, owns no collection, and carries EVERY checkpoint of
        // the plan — assembled here from the days, never asked of the model (v0 asked, and the
        // answer drifted from the days it was supposed to copy).
        $days[] = new ComputedDay(
            index: $introDays + 1,
            kind: PlanDayKind::Final,
            title: $outline->finalDayTitle !== '' ? $outline->finalDayTitle : 'Прогон перед событием',
            scheduledOn: $event,
            termBudget: 0,
            skills: [],
            checkpoints: $this->checkpointsOf($days),
            role: null,
            topics: [],
            sourceDayIndex: null,
        );

        return new ComputedPlan(
            days: $days,
            need: $need,
            capacity: $capacity,
            maxDays: $maxDays,
            introDays: $introDays,
            restDays: $restDays,
            fits: $dropped === [],
            dropped: $dropped,
            spacing: $spacing,
            finalSameDay: false,
        );
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
        // case the one day left is both and the learner is going to have to compress.
        $introDaysRemaining = max(1, $daysRemaining - 1);

        $skills = [];
        foreach ($remainingIntroDays as $day) {
            foreach ($day->skills as $skill) {
                $skills[] = $skill;
            }
        }

        $needRemaining = $this->sum($skills);
        [, $atRisk] = $this->fitInOrder($skills, $capacity * $introDaysRemaining);

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
     * How many terms one day holds at this many minutes.
     *
     * Piecewise-linear through the three anchors, extended at both ends with the slope of the
     * nearest segment. A figure between anchors interpolates, which is what the prompt tells the
     * model to do with the same table — one rule, stated twice, in the two places that must agree.
     * Never below 1: a day that holds nothing is not a day.
     */
    public function capacityFor(int $minutesPerDay): int
    {
        $anchors = self::CAPACITY_ANCHORS;
        if (isset($anchors[$minutesPerDay])) {
            return $anchors[$minutesPerDay];
        }

        $points = [];
        foreach ($anchors as $minutes => $terms) {
            $points[] = [$minutes, $terms];
        }

        // Which segment governs: the one containing the value, else the nearest end's.
        $last = count($points) - 1;
        $i = 0;
        while ($i < $last - 1 && $minutesPerDay > $points[$i + 1][0]) {
            $i++;
        }

        [$x0, $y0] = $points[$i];
        [$x1, $y1] = $points[$i + 1];
        $span = $x1 - $x0;
        if ($span === 0) {
            return max(1, $y0);   // two anchors at the same minute count: unreachable, not divided by
        }

        return max(1, (int) round($y0 + ($minutesPerDay - $x0) * (($y1 - $y0) / $span)));
    }

    /**
     * Take abilities in order until the room runs out.
     *
     * ORDER, not size: a greedy pack by size would keep the cheap abilities and drop an expensive
     * one from the middle, which in a dependency-ordered plan means teaching day 3 to a learner who
     * never got day 2. The plan is a sequence; it is truncated, not filtered.
     *
     * @param  list<PlanSkill>  $skills
     * @return array{0: list<PlanSkill>, 1: list<PlanSkill>}  kept, dropped
     */
    private function fitInOrder(array $skills, int $room): array
    {
        $kept = [];
        $dropped = [];
        $spent = 0;

        foreach ($skills as $skill) {
            if ($dropped === [] && $spent + $skill->estTerms <= $room) {
                $kept[] = $skill;
                $spent += $skill->estTerms;

                continue;
            }
            $dropped[] = $skill;
        }

        return [$kept, $dropped];
    }

    /**
     * Share the abilities out over exactly `$dayCount` days, in order.
     *
     * By CUMULATIVE POSITION rather than by first-fit: an ability goes to the day its running total
     * lands in. First-fit fragments — three 5-term abilities at capacity 9 come out as three days
     * instead of two, because the second never fits beside the first — and a plan that grew a day
     * out of a rounding decision is a plan that ends on the wrong date.
     *
     * A day may end up one or two terms over capacity when the abilities do not divide (three 5s
     * into two 9s is 10 and 5). That is the honest answer: the arithmetic says two days, the chunks
     * do not split, and the day's real budget is reported rather than trimmed to look tidy.
     *
     * @param  list<PlanSkill>  $skills
     * @return list<list<PlanSkill>>  exactly `$dayCount` buckets, each with at least one ability
     */
    private function packIntoDays(array $skills, int $capacity, int $dayCount): array
    {
        /** @var list<list<PlanSkill>> $buckets */
        $buckets = array_fill(0, $dayCount, []);

        $cumulative = 0;
        foreach ($skills as $skill) {
            $day = min($dayCount - 1, intdiv($cumulative, $capacity));
            $buckets[$day][] = $skill;
            $cumulative += $skill->estTerms;
        }

        // An empty day is a day the learner opens onto nothing. It can only happen when there are
        // fewer abilities than days, so the fix is to spend a day rather than to show an empty one:
        // steal from the fullest earlier bucket, keeping order.
        for ($i = 1; $i < $dayCount; $i++) {
            if ($buckets[$i] !== []) {
                continue;
            }
            for ($j = $i - 1; $j >= 0; $j--) {
                if (count($buckets[$j]) > 1) {
                    $moved = array_pop($buckets[$j]);
                    $buckets[$i] = [$moved];
                    break;
                }
            }
        }

        return array_values(array_filter($buckets, static fn (array $b): bool => $b !== []));
    }

    /**
     * Build one teaching day out of the abilities that landed on it.
     *
     * The TITLE prefers the outline day's own words when the whole day came from one outline day —
     * which is the ordinary case and is why P1 is asked for a title at all: «Начать приём и описать
     * боль» is a step, and the first ability, «сказать, где именно болит и как давно», is a
     * fragment of one. When the server has merged material from several outline days there is no
     * such title to borrow, and the day is named after its main ability, which is the honest
     * fallback rather than a title about a day that no longer exists.
     *
     * @param  list<PlanSkill>  $skills
     * @param  list<string>|null  $finalCheckpoints  the whole plan's checkpoints, on a same-day plan
     */
    private function introDay(
        int $index,
        PlanDayKind $kind,
        DateTimeImmutable $scheduledOn,
        array $skills,
        PlanOutline $outline,
        ?array $finalCheckpoints,
        int $budget,
    ): ComputedDay {
        $sources = [];
        foreach ($skills as $skill) {
            $sources[$skill->sourceDayIndex] = true;
        }
        $sourceIndex = count($sources) === 1 ? (int) array_key_first($sources) : null;
        $sourceDay = $sourceIndex !== null ? $outline->day($sourceIndex) : null;

        $checkpoints = [];
        foreach ($skills as $skill) {
            if ($skill->checkpoint !== null && $skill->checkpoint !== '') {
                $checkpoints[] = $skill->checkpoint;
            }
        }

        $topics = [];
        foreach ($skills as $skill) {
            $day = $outline->day($skill->sourceDayIndex);
            if ($day === null) {
                continue;
            }
            foreach ($day->topics as $topic) {
                if (! in_array($topic, $topics, true)) {
                    $topics[] = $topic;
                }
            }
        }

        // The role belongs to the day's MAIN ability — the first one. A merged day has one
        // conversation, not two, and the person the learner talks to is the one the day opens with.
        $role = $this->roleFor($skills, $outline);

        return new ComputedDay(
            index: $index,
            kind: $kind,
            title: $sourceDay?->title !== null && $sourceDay->title !== ''
                ? $sourceDay->title
                : ($skills[0]->outcome ?? 'День плана'),
            scheduledOn: $scheduledOn,
            termBudget: $budget,
            skills: $skills,
            checkpoints: $finalCheckpoints ?? $checkpoints,
            role: $role,
            topics: $topics,
            sourceDayIndex: $sourceIndex,
        );
    }

    /** @param list<PlanSkill> $skills */
    private function roleFor(array $skills, PlanOutline $outline): ?PlanRole
    {
        if ($skills === []) {
            return null;
        }

        $sourceRole = $outline->day($skills[0]->sourceDayIndex)?->role;
        if ($sourceRole === null) {
            return null;
        }

        // The checkpoints on the role must be THIS day's, in THIS day's order — the model wrote
        // them against its own day, and a merged day would otherwise hand the generator a
        // checkpoint list that its own material was never asked to cover.
        $checkpoints = [];
        foreach ($skills as $skill) {
            if ($skill->checkpoint !== null && $skill->checkpoint !== '') {
                $checkpoints[] = $skill->checkpoint;
            }
        }

        return new PlanRole(
            name: $sourceRole->name,
            openingLines: $sourceRole->openingLines,
            checkpoints: $checkpoints,
            ifSilent: $sourceRole->ifSilent,
        );
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
