<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * THE FINAL DAY IS REACHABLE — Д-27.
 *
 * It introduces nothing and owns no collection, so asking to GENERATE it is a 404 by design: there
 * is no material to buy. The material already exists — every card the plan has taught — and the
 * live run found that out the hard way: the client drew «Собрать день», the server answered 404,
 * and the plan could not be finished from the app at all. It was completed from tinker.
 *
 * Two things make it passable, and both are the smallest version of themselves (the finished
 * final-day SCREEN is DAY-2): the session of the final day is a run-through over the whole plan,
 * and there is an endpoint that says «подготовка завершена».
 */
beforeEach(fn () => fakePlanModel());

it('deals the final day a run of every scene, one after another', function () {
    // Наряд SCENE-RUN, Ч.2.8: старый «прогон по всем карточкам плана» заменён цепочкой прогонов
    // сцен той же механикой. Раньше последнее утро было списком из полусотни карточек по одной на
    // термин; теперь это разговоры, сыгранные подряд, — то, что человеку предстоит через час.
    [, $token, $planId] = startedPlan($this);

    $final = DB::table('learning_plan_days')->where('plan_id', $planId)->where('kind', 'final')->first();
    expect($final)->not->toBeNull()
        // The premise: no collection of its own, which is why generating it is a 404.
        ->and($final->collection_id)->toBeNull();

    $session = planSession($this, $token, $planId, (int) $final->day_index);

    // Every card comes from a day that HAS been written, once each.
    $taught = DB::table('collection_items')
        ->whereIn('collection_id', DB::table('learning_plan_days')->where('plan_id', $planId)
            ->whereNotNull('collection_id')->pluck('collection_id'))
        ->pluck('term_id')->unique()->values()->all();

    $dealt = array_map(static fn (array $t): string => $t['card']['term_id'], $session['tasks']);

    expect($session['tasks'])->not->toBe([])
        ->and(array_diff($dealt, $taught))->toBe([])
        ->and(count($dealt))->toBe(count(array_unique($dealt)))
        // A REHEARSAL, not a lesson: it schedules nothing and closes no stage, so the plan cannot go
        // backwards on its last morning.
        ->and($session['strict'])->toBeFalse()
        ->and(DB::table('study_sessions')->where('id', $session['session_id'])->value('is_practice'))->toBeTrue();

    foreach ($session['tasks'] as $task) {
        expect($task['source'])->toBe('scene_run')
            ->and($task['section_code'])->toBe('scene_run')
            ->and($task['turn_level'])->toBe('say');
    }

    // …и разговор каждой сцены приезжает целиком: лента прогона это та же цепочка.
    expect($session['dialogues'])->not->toBe([]);
});

it('runs the final day by voice alone — no options, no blocks, no keyboard (С-9)', function () {
    // Канон §10/§12: «прогон всех сцен + финальный разговор», вслух. What the stand got instead was
    // the ordinary selector's pick over 53 tasks — `listening` 16, `typing` 11, `cloze` 4, and not
    // one speaking card — i.e. the learner typing out the INTERLOCUTOR's lines from audio three
    // minutes before the appointment. Теперь режим один и он назван: это ступень C.
    [, $token, $planId] = startedPlan($this);

    $final = DB::table('learning_plan_days')->where('plan_id', $planId)->where('kind', 'final')->first();
    $session = planSession($this, $token, $planId, (int) $final->day_index);

    $modes = array_values(array_unique(array_map(
        static fn (array $t): string => (string) $t['card']['exercise_mode'],
        $session['tasks'],
    )));

    expect($modes)->toBe(['speaking']);

    foreach ($session['tasks'] as $task) {
        // Ни вариантов, ни блоков: на экране подсказка и микрофон.
        expect($task['card']['options'])->toBeNull()
            ->and($task['card']['chips'])->toBeNull()
            // Только свои ходы. Реплика собеседника звучит из ленты и ходом не является.
            ->and($task['shelf'])->toBeIn(['say', 'ask']);
    }
});

it('runs an unripe scene on the last day anyway, and says it was unripe', function () {
    // Единственное исключение из гейта (наряд Ч.2.8): завтра стойка, и сцена, до которой лестница не
    // дошла, — ровно то место, где будет страшно. Прогоняется, но помечена.
    [, $token, $planId] = startedPlan($this);

    $final = DB::table('learning_plan_days')->where('plan_id', $planId)->where('kind', 'final')->first();
    $session = planSession($this, $token, $planId, (int) $final->day_index);

    expect($session['dialogues'])->not->toBe([]);
    foreach ($session['dialogues'] as $dialogue) {
        // Ни одна сцена этого плана ещё не проходила ступень B: человек только что его создал.
        expect($dialogue['run_ready'])->toBeFalse();
    }
});

it('lets the learner close the plan, and the words go to the archive with it', function () {
    [$user, $token, $planId] = startedPlan($this);

    $before = DB::table('user_term_progress')->where('user_id', $user->id)
        ->whereNotNull('enrolled_at')->count();
    expect($before)->toBeGreaterThan(0);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/plans/{$planId}/complete")
        ->assertOk()
        ->assertJsonPath('data.status', 'completed');

    expect(DB::table('learning_plans')->where('id', $planId)->value('completed_at'))->not->toBeNull()
        // «Завершённый план — это архив, и он уносит свои слова с собой» — the same archive
        // `abandon` performs, through the same handler.
        ->and(DB::table('user_term_progress')->where('user_id', $user->id)->whereNotNull('enrolled_at')->count())
        ->toBe(0)
        // Nothing is deleted: the log, the stages and the schedule are all still there.
        ->and(DB::table('user_term_progress')->where('user_id', $user->id)->count())->toBe($before);
});

it('refuses a second complete the way every other ending does', function () {
    // 409, exactly as `pause` and `abandon` answer a plan that is already ended: the transition
    // rules are the plan's own and this endpoint is not a new set of them. The device reads it as
    // «already done» rather than as a failure — the run it is reporting really did finish.
    [, $token, $planId] = startedPlan($this);

    $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/plans/{$planId}/complete")->assertOk();
    $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/plans/{$planId}/complete")
        ->assertStatus(409);
});

it('404s a complete for somebody else’s plan', function () {
    [, $ownerToken, $planId] = startedPlan($this);
    [, $strangerToken] = learner();

    // The guard caches the user it resolved for the FIRST request of a test, and every request in
    // one test shares an application instance — without this the second bearer token is never read.
    app('auth')->forgetGuards();

    $this->withHeader('Authorization', "Bearer {$strangerToken}")
        ->postJson("/api/v1/plans/{$planId}/complete")
        ->assertNotFound();
});
