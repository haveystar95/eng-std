<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * A SITTING IS NOT A DAY — the warm-up, and the intro, after the owner's live day 1 of 02.09.
 *
 * Plan `01M1HZF4DDFHMP5YWSVTVBR3TN`, «Снять квартиру…», 30 cards: 25 of the scene and the plan's
 * five rescue phrases. The first sitting dealt 106 tasks and was answered. Then, with every card
 * of the scene answered correctly, the day kept dealing sittings of SEVEN — the same five rescue
 * phrases every time, plus one card of the scene that could not close. Six such sittings between
 * 21:48 and 22:03, and no way to finish the day.
 *
 * Two mechanisms, and this file pins both:
 *
 *   the warm-up  «из ротации не выпадают» was implemented as «always in the deal»: a phrase that
 *                owed nothing was still given one card, in EVERY sitting. The rule is a DAY —
 *                answered today, back tomorrow (решение владельца).
 *   the intro    the card «available» had been met in an earlier plan, so `term_exposures` held a
 *                row dated before it joined this one. The write was an ignored insert, so the six
 *                fresh exposures the client uploaded (21:50 … 22:03) were all dropped, the intro
 *                step never closed — and that ONE card is what actually held day 1 open.
 */
beforeEach(function (): void {
    fakePlanModel();
    // The plan ladder deals `intro` and `speaking`, and both ship dark on a fresh account.
    DB::table('learning_mode_settings')->where('scope', 'global')->whereNull('user_id')->update(['enabled' => true]);
});

/** The rescue kit of this plan — the five cards the warm-up is made of. */
function rescueTermIds(string $planId): array
{
    $collectionId = DB::table('learning_plan_days')
        ->where('plan_id', $planId)->where('day_index', 1)->value('collection_id');

    return DB::table('collection_items as ci')
        ->join('terms as t', 't.id', '=', 'ci.term_id')
        ->where('ci.collection_id', $collectionId)
        ->where('t.shelf', 'rescue')
        ->pluck('ci.term_id')
        ->all();
}

it('deals the rescue kit once a day, not once a sitting', function () {
    [, $token, $planId] = startedPlan($this);

    $rescue = rescueTermIds($planId);
    expect($rescue)->toHaveCount(5);

    $first = planSession($this, $token, $planId, 1);
    $warmup = array_values(array_filter(
        $first['tasks'],
        static fn (array $t): bool => ($t['section'] ?? null) === 'warmup',
    ));
    expect($warmup)->not->toBeEmpty('the kit opens the day');

    // ONLY the warm-up is answered, and the sitting is abandoned there. That is what makes this a
    // test about the kit rather than about the day: with the whole day answered the day PASSES and
    // the next request for it is a practice run of a finished day, where every card comes back by
    // design. The learner who quits after the warm-up is exactly the case the live loop hit.
    answerTasks($this, $token, ['session_id' => $first['session_id'], 'tasks' => $warmup]);

    // The SAME day, a second sitting. Everything the kit owed was answered a minute ago.
    $second = planSession($this, $token, $planId, 1);
    expect($second['tasks'])->not->toBeEmpty('the day itself is still owed');
    $again = array_values(array_filter(
        $second['tasks'],
        static fn (array $t): bool => in_array($t['card']['term_id'], $rescue, true),
    ));

    expect($again)->toBe([], 'a phrase answered today must not come back in the next sitting');
});

it('brings the kit back after a night, on its next stage', function () {
    [$user, $token, $planId] = startedPlan($this);

    answerTasks($this, $token, planSession($this, $token, $planId, 1));
    ageHistory($user->id, 1);

    $next = planSession($this, $token, $planId, 1);
    $warmup = array_values(array_filter(
        $next['tasks'],
        static fn (array $t): bool => in_array($t['card']['term_id'], rescueTermIds($planId), true),
    ));

    expect($warmup)->not->toBeEmpty('«разогрев каждый день» is a day, and a night has passed');
});

it('closes the introduction of a card an earlier plan had already shown', function () {
    [$user, $token, $first] = startedPlan($this);

    // Day 1 of a plan the learner then abandons — every card of it now carries an exposure.
    answerTasks($this, $token, planSession($this, $token, $first, 1));
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/plans/{$first}/abandon", ['reason' => 'передумал'])
        ->assertOk();

    // A SECOND, so the new plan's collection is stamped after the old plan's answers — both sides
    // of the cutoff are `timestamp(0)` and this fixture runs inside one of them.
    $this->travel(5)->seconds();

    $second = startedPlanFor($this, $token);
    $session = planSession($this, $token, $second, 1);

    // Every card of the new plan is introduced again — its own ladder, its own cutoff.
    $intros = array_values(array_filter(
        $session['tasks'],
        static fn (array $t): bool => $t['card']['exercise_mode'] === 'intro',
    ));
    expect($intros)->not->toBeEmpty();

    answerTasks($this, $token, $session);

    // ...and the SHOWING is recorded, so the step is closed. Before this, the second plan's
    // exposures were dropped by an ignored insert and the same cards were introduced again in
    // every sitting of the day — six times over, on the owner's live run.
    $reIntroduced = array_values(array_filter(
        planSession($this, $token, $second, 1)['tasks'],
        static fn (array $t): bool => $t['card']['exercise_mode'] === 'intro',
    ));

    expect($reIntroduced)->toBe([], 'a card shown in this plan is introduced once');
});

it('passes a day whose every scene card was answered, in one sitting', function () {
    [, $token, $planId] = startedPlan($this);

    answerTasks($this, $token, planSession($this, $token, $planId, 1));

    // The focus moves off day 1 the moment the scene has closed stage A — the kit does not hold it
    // open, and neither does a card whose intro was shown.
    expect(planSession($this, $token, $planId)['focus_day_index'])->toBeGreaterThan(1);
});
