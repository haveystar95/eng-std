<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * A PLAN DEALS BY THE PLAN — Э4.2 of E2E-SIM-2 (С-2), as a test.
 *
 * The seam and the warm-up used to be chosen by `due_at`: a card of an earlier day reached the
 * sitting only if the ORDINARY repetition planner had made it due, while its STAGE was computed
 * from the plan's own ladder. The two agree for as long as SM-2's interval happens to be shorter
 * than the plan, and they part company on the second night of any plan that goes well.
 *
 * Measured on the stand: `PlanStandings` said all sixteen cards of day 1 stood on stage B with a
 * `nextMode` and all five rescue phrases stood on B owing `listening` — and the sitting built at
 * that exact moment contained neither. Their `due_at` had gone out to 2026-10-21…2027-01-09 and
 * 2027-09-12 respectively, so «просроченных 0», so nothing was dealt. «Готовность плана» was
 * unreachable by construction and the rescue kit quietly left the rotation after two days.
 *
 * Every test below pushes `due_at` a season away — which is what a couple of correct answers
 * actually does — and then asks for the sitting.
 */
beforeEach(function (): void {
    fakePlanModel();
    DB::table('learning_mode_settings')->where('scope', 'global')->whereNull('user_id')->update(['enabled' => true]);
});

/**
 * WHAT A FEW CORRECT ANSWERS DO TO THE SCHEDULE — every plan card owed months from now.
 *
 * A season, so no reading of «просрочено» can reach any of them. It is the state the stand was in
 * when it took the measurement, produced there by answering correctly twice; done here in one
 * statement, because the point is the SELECTION rule and not SM-2's arithmetic.
 */
function scheduleFarAway(string $userId): void
{
    DB::table('user_term_progress')->where('user_id', $userId)->update(['due_at' => now()->addDays(90)]);
}

it('deals the seam off the plan`s ladder, not off due_at — a season out and still dealt (Э4.2)', function () {
    [$user, $token, $planId] = startedPlan($this, ['event_date' => now()->addDays(10)->format('Y-m-d')]);

    walkDay($this, $token, $planId, 1);
    ageHistory($user->id, days: 1);
    scheduleFarAway($user->id);

    $session = planSession($this, $token, $planId);
    expect($session['day_index'])->toBe(2);

    $bySection = [];
    foreach ($session['tasks'] as $task) {
        $bySection[$task['section']][] = $task;
    }

    // THE SEAM IS THERE, it is day 1's, and it is on stage B.
    expect($bySection['review'] ?? [])->not->toBeEmpty();
    $seamModes = [];
    foreach ($bySection['review'] as $task) {
        $seamModes[] = $task['card']['exercise_mode'];
        expect($task['stage'])->toBe('b')
            ->and($task['from_day_index'])->toBe(1)
            ->and($task['source'])->toBe('plan_review');
    }

    // …and its trainers are SIT-1's table for stage B, which is what proves the seam came off the
    // ladder rather than off a queue: a `due_at` selection could only have produced the same cards
    // by coincidence, and did not produce them at all.
    expect($seamModes)->toContain('situational_hear')
        ->and($seamModes)->toContain('situational_say');

    // THE WARM-UP IS THERE TOO, on the kit's own accelerated rung.
    expect($bySection['warmup'] ?? [])->not->toBeEmpty();
    foreach ($bySection['warmup'] as $task) {
        expect($task['shelf'])->toBe('rescue');
    }

    // And the day itself is still the day: stage A of day 2's own cards, none of it touched by any
    // of the above.
    expect($bySection['day'] ?? [])->not->toBeEmpty();
    foreach ($bySection['day'] as $task) {
        expect($task['stage'])->toBe('a')
            ->and($task['from_day_index'])->toBe(2);
    }
});

it('keeps dealing a card up to the ceiling of its own shelf, however far the schedule says', function () {
    // «После починки B-карточки продолжают раздаваться до C-потолка своей полки»: a word's last
    // stage is C, a line's is B, and neither of them is «whenever SM-2 next says so».
    //
    // THREE SCENES, because a two-day plan runs out of teaching days before its day 1 reaches stage
    // C, and the final day is a run-through rather than a lesson with a seam in it.
    [$user, $token, $planId] = startedPlan($this, [
        'goal_text' => 'Иду к врачу, болит спина, надо объяснить и понять назначение [scenes:3]',
        'event_date' => now()->addDays(10)->format('Y-m-d'),
    ]);

    // `client_seq` is per learner and monotonic — it is the order progress is folded in — so every
    // batch continues the count. A second walk restarting at 1 would be answering «before» the
    // first one, and the ladder would read the nights in the wrong order.
    $seq = walkDay($this, $token, $planId, 1);
    ageHistory($user->id, days: 1);
    scheduleFarAway($user->id);

    // Night 1 → day 1's cards stand on stage B and are dealt in day 2's seam. Answering the whole
    // sitting walks that stage and closes day 2's own.
    $seq = answerTasks($this, $token, planSession($this, $token, $planId), $seq);
    ageHistory($user->id, days: 1);
    scheduleFarAway($user->id);

    // Night 2 → day 3 is the focus, and day 1's WORDS have a stage C to climb. Their `due_at` is a
    // season out, so under the old rule this seam was empty.
    $session = planSession($this, $token, $planId);
    expect($session['day_index'])->toBe(3);

    $stagesOfDay1 = [];
    foreach ($session['tasks'] as $task) {
        if (($task['from_day_index'] ?? null) === 1 && $task['section'] === 'review') {
            $stagesOfDay1[] = $task['stage'];
        }
    }

    expect($stagesOfDay1)->toContain('c');
});

it('brings the rescue kit back after its stages are over — every other day, in its own two modes', function () {
    // С-5/Ч-4. `PlanStageLadder::STEPS[line][C]` is empty, so a rescue phrase that closed stage B
    // used to become `finished = true, next = null` — and the warm-up dealt it «one card at whatever
    // rung the pair stands on», which on the stand meant it was never dealt again at all. Канон §5
    // says the kit trains harder than anything else and «из ротации не выпадает».
    //
    // Three scenes, so the plan still has a teaching day (and therefore a warm-up) on the third
    // night — the final day is a run-through and does not warm up.
    [$user, $token, $planId] = startedPlan($this, [
        'goal_text' => 'Иду к врачу, болит спина, надо объяснить и понять назначение [scenes:3]',
        'event_date' => now()->addDays(10)->format('Y-m-d'),
    ]);

    $seq = walkDay($this, $token, $planId, 1);
    ageHistory($user->id, days: 1);
    scheduleFarAway($user->id);

    // Night 1: the kit is dealt its stage B and answered. The sequence continues — see the note in
    // the test above about `client_seq`.
    $session = planSession($this, $token, $planId);
    expect(rescueTasksOf($session))->not->toBeEmpty();
    answerTasks($this, $token, $session, $seq);

    // Night 2 — the kit answered YESTERDAY, so it rests. «Раз в 2 дня», anchored on the phrase's own
    // history rather than on a calendar parity.
    ageHistory($user->id, days: 1);
    scheduleFarAway($user->id);
    expect(rescueTasksOf(planSession($this, $token, $planId)))->toBeEmpty();

    // Night 3 — and it is back, in a maintenance mode: heard, or said. Never `cloze`/`typing`, which
    // is where the ordinary selector used to walk the five phrases.
    ageHistory($user->id, days: 1);
    scheduleFarAway($user->id);
    $maintenance = rescueTasksOf(planSession($this, $token, $planId));
    expect($maintenance)->not->toBeEmpty();
    foreach ($maintenance as $task) {
        expect($task['card']['exercise_mode'])->toBeIn(['listening', 'speaking'])
            ->and($task['section'])->toBe('warmup');
    }
});

it('never calls a rescue phrase finished — it has no last day while the plan runs', function () {
    [$user, $token, $planId] = startedPlan($this, ['event_date' => now()->addDays(10)->format('Y-m-d')]);

    $seq = walkDay($this, $token, $planId, 1);
    ageHistory($user->id, days: 1);
    answerTasks($this, $token, planSession($this, $token, $planId), $seq);
    ageHistory($user->id, days: 2);

    $terms = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/v1/plans/{$planId}/days/1")
        ->assertOk()
        ->json('data.terms');

    $rescue = array_values(array_filter($terms, static fn (array $t): bool => ($t['shelf'] ?? null) === 'rescue'));
    expect($rescue)->not->toBeEmpty();
    foreach ($rescue as $term) {
        expect($term['finished'] ?? null)->toBeFalsy();
    }
});

/** The rescue kit's tasks in one session payload. @return list<array<string, mixed>> */
function rescueTasksOf(array $session): array
{
    return array_values(array_filter(
        $session['tasks'],
        static fn (array $t): bool => ($t['shelf'] ?? null) === 'rescue',
    ));
}
