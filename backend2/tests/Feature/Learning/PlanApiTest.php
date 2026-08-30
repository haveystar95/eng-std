<?php

declare(strict_types=1);

use App\Modules\Generation\Application\Service\PlanDayComposer;
use App\Modules\Generation\Infrastructure\Adapter\FakePlanContentModel;
use App\Modules\Generation\Infrastructure\Prompt\PlanPromptLibrary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * `/api/v1/plans` — THE WHOLE FLOW, on the offline model.
 *
 * The vendor is never reached: {@see FakePlanContentModel} answers both prompts with material that
 * obeys the numbers the server hands it and therefore PASSES the validators. That is the point — a
 * fake that failed validation would make every one of these a test of the failure path.
 *
 * The queue is `sync` under test, so `POST /plans/{id}/start` generates day 1 inside the request.
 * The chaining of day 2 is asserted through the day rows rather than through the queue, because
 * what matters is the RULE («one day at a time, the next when the previous is ready»), not the
 * mechanism that carries it.
 */
// Both plan calls, offline — and RESOLVED BACK to prove the binding landed. The two lines this
// replaces were written by hand in every plan test file, and one file wrote the port's namespace
// wrong; see fakePlanModel() in tests/Pest.php.
beforeEach(fn () => fakePlanModel());

function createPlan(object $ctx, string $token, array $overrides = []): array
{
    $payload = [
        'goal_text' => 'Иду к врачу, болит спина, надо объяснить и понять назначение',
        'target_lang' => 'en',
        'level' => 'basic',
        'event_date' => now()->addDays(2)->format('Y-m-d'),
        'minutes_per_day' => 20,
        ...$overrides,
    ];

    return $ctx->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/plans', $payload)
        ->assertCreated()
        ->json('data');
}

function outlinePlan(object $ctx, string $token, string $planId): array
{
    return $ctx->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/plans/{$planId}/outline")
        ->assertOk()
        ->json('data');
}

// ── the draft ─────────────────────────────────────────────────────────────────────────────────

it('creates a draft that costs nothing — no day, no model call, no word held', function () {
    [$user, $token] = learner();
    profileFor($user, ['native_language' => 'ru', 'target_language' => 'en']);

    $plan = createPlan($this, $token);

    expect($plan['status'])->toBe('draft')
        ->and($plan['days'])->toBe([])
        ->and($plan['computed'])->toBeNull()
        ->and($plan['goal_restated'])->toBeNull()
        // The support language is the ACCOUNT's, never asked for and never stored on the plan.
        ->and($plan['support_lang'])->toBe('ru');
});

it('refuses an event date that has already passed', function () {
    [$user, $token] = learner();
    profileFor($user, ['native_language' => 'ru']);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/plans', [
            'goal_text' => 'Иду к врачу, болит спина',
            'target_lang' => 'en',
            'level' => 'basic',
            'event_date' => now()->subDay()->format('Y-m-d'),
        ])
        ->assertStatus(422)
        ->assertJsonPath('code', 'event_date_in_past');
});

// ── the skeleton ──────────────────────────────────────────────────────────────────────────────

it('builds the skeleton and lays the days on the calendar itself', function () {
    [$user, $token] = learner();
    profileFor($user, ['native_language' => 'ru']);

    $plan = createPlan($this, $token, ['event_date' => now()->addDays(2)->format('Y-m-d')]);
    $plan = outlinePlan($this, $token, $plan['id']);

    // Three days to the event → two teaching days and a final one; the final day is on the event.
    expect($plan['computed']['max_days'])->toBe(3)
        ->and($plan['computed']['capacity'])->toBe(9)
        ->and($plan['days'])->toHaveCount(3)
        ->and($plan['days'][2]['kind'])->toBe('final')
        ->and($plan['days'][2]['collection_id'])->toBeNull()
        ->and($plan['days'][2]['scheduled_on'])->toBe(now()->addDays(2)->format('Y-m-d'))
        // Every checkpoint of the plan, assembled by the SERVER, on the final day.
        ->and($plan['days'][2]['checkpoints'])->toHaveCount(4)
        ->and($plan['status'])->toBe('draft');
});

it('re-outlining replaces the days rather than adding to them', function () {
    [$user, $token] = learner();
    profileFor($user, ['native_language' => 'ru']);

    $plan = createPlan($this, $token);
    outlinePlan($this, $token, $plan['id']);
    $again = outlinePlan($this, $token, $plan['id']);

    expect($again['days'])->toHaveCount(3)
        ->and(DB::table('learning_plan_days')->where('plan_id', $plan['id'])->count())->toBe(3);
});

// ── the free adjustment ───────────────────────────────────────────────────────────────────────

it('recomputes the calendar with no model call when the minutes change', function () {
    [$user, $token] = learner();
    profileFor($user, ['native_language' => 'ru']);

    $plan = createPlan($this, $token, ['event_date' => now()->addDays(4)->format('Y-m-d')]);
    $outlined = outlinePlan($this, $token, $plan['id']);
    $outlineBefore = DB::table('learning_plans')->where('id', $plan['id'])->value('outline');

    $after = $this->withHeader('Authorization', "Bearer {$token}")
        ->patchJson("/api/v1/plans/{$plan['id']}/outline", ['minutes_per_day' => 40])
        ->assertOk()
        ->json('data');

    // The model's answer is untouched; only the arithmetic moved.
    expect(DB::table('learning_plans')->where('id', $plan['id'])->value('outline'))->toBe($outlineBefore)
        ->and($after['minutes_per_day'])->toBe(40)
        ->and($after['computed']['capacity'])->toBe(16)
        ->and($after['computed']['capacity'])->not->toBe($outlined['computed']['capacity']);
});

it('drops a day and re-indexes the rest, editing the stored outline with it', function () {
    [$user, $token] = learner();
    profileFor($user, ['native_language' => 'ru']);

    $plan = createPlan($this, $token, ['event_date' => now()->addDays(4)->format('Y-m-d')]);
    $before = outlinePlan($this, $token, $plan['id']);
    $introBefore = $before['computed']['intro_days'];

    $after = $this->withHeader('Authorization', "Bearer {$token}")
        ->patchJson("/api/v1/plans/{$plan['id']}/outline", ['drop_day_index' => 1])
        ->assertOk()
        ->json('data');

    expect($after['computed']['intro_days'])->toBeLessThan($introBefore)
        // The stored outline moved too — otherwise the next reschedule would bring the day back.
        ->and(count(json_decode((string) DB::table('learning_plans')->where('id', $plan['id'])->value('outline'), true)['days']))
        ->toBe($introBefore - 1);
});

it('refuses a PATCH that changes nothing', function () {
    [$user, $token] = learner();
    profileFor($user, ['native_language' => 'ru']);

    $plan = createPlan($this, $token);
    outlinePlan($this, $token, $plan['id']);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->patchJson("/api/v1/plans/{$plan['id']}/outline", [])
        ->assertStatus(422);
});

// ── the commitment ────────────────────────────────────────────────────────────────────────────

it('starts the plan, writes day 1 as a real collection and enrols its terms strictly', function () {
    [$user, $token] = learner();
    profileFor($user, ['native_language' => 'ru']);

    $plan = createPlan($this, $token);
    outlinePlan($this, $token, $plan['id']);

    $started = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/plans/{$plan['id']}/start")
        ->assertOk()
        ->json('data');

    expect($started['status'])->toBe('active');

    $day1 = DB::table('learning_plan_days')->where('plan_id', $plan['id'])->where('day_index', 1)->first();

    expect($day1->status)->toBe('ready')
        ->and($day1->collection_id)->not->toBeNull()
        ->and($day1->generation_attempts)->toBe(1);

    // A real collection with real terms — every trainer works on it without knowing plans exist.
    $termIds = DB::table('collection_items')->where('collection_id', $day1->collection_id)->pluck('term_id');
    expect($termIds)->toHaveCount(9);

    // Strictly enrolled, with the plan named as the reason.
    $sources = DB::table('user_term_progress')
        ->where('user_id', $user->id)
        ->whereIn('term_id', $termIds)
        ->pluck('enrollment_sources');

    expect($sources)->toHaveCount(9);
    foreach ($sources as $raw) {
        expect(json_decode((string) $raw, true))->toBe(['plan:' . $plan['id']]);
    }
});

it('writes the two plan facts onto the terms — is_line and a difficulty score', function () {
    [$user, $token] = learner();
    profileFor($user, ['native_language' => 'ru']);

    $plan = createPlan($this, $token);
    outlinePlan($this, $token, $plan['id']);
    $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/plans/{$plan['id']}/start")->assertOk();

    $collectionId = DB::table('learning_plan_days')->where('plan_id', $plan['id'])->where('day_index', 1)->value('collection_id');
    $terms = DB::table('collection_items as ci')
        ->join('terms as t', 't.id', '=', 'ci.term_id')
        ->where('ci.collection_id', $collectionId)
        ->get(['t.is_line', 't.difficulty_score']);

    // 9 terms → ceil(0.45 × 9) = 5 replies.
    expect($terms->where('is_line', true))->toHaveCount(5)
        ->and($terms->where('is_line', false))->toHaveCount(4)
        ->and($terms->whereNull('difficulty_score'))->toHaveCount(0);
});

it('scopes the day example to the day collection, never to the term at large', function () {
    [$user, $token] = learner();
    profileFor($user, ['native_language' => 'ru']);

    $plan = createPlan($this, $token);
    outlinePlan($this, $token, $plan['id']);
    $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/plans/{$plan['id']}/start")->assertOk();

    $collectionId = DB::table('learning_plan_days')->where('plan_id', $plan['id'])->where('day_index', 1)->value('collection_id');

    expect(DB::table('term_examples')->where('scope_collection_id', $collectionId)->count())->toBeGreaterThan(0);
});

/**
 * A SHORT plan is written whole at the start — and the final day is still never sent to a model.
 *
 * The «one day at a time» rule PLAN-1a shipped is now the rule for LONG plans only
 * ({@see \App\Modules\Learning\Domain\Service\PlanGenerationPolicy}): three teaching days or fewer
 * and there is no meaningful abandonment window to protect, while making the learner watch a
 * spinner on day 2 is a real cost. The long-plan half of the split is asserted in PlanSessionTest,
 * where a day can actually be walked to `done`.
 */
it('writes a short plan whole at the start, and never sends the final day to a model', function () {
    [$user, $token] = learner();
    profileFor($user, ['native_language' => 'ru']);

    $plan = createPlan($this, $token, ['event_date' => now()->addDays(2)->format('Y-m-d')]);
    outlinePlan($this, $token, $plan['id']);
    $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/plans/{$plan['id']}/start")->assertOk();

    // Two teaching days, so the whole plan is eager. The queue is `sync`, so both were written
    // inside the request.
    $days = DB::table('learning_plan_days')->where('plan_id', $plan['id'])->orderBy('day_index')->get();

    expect($days[0]->status)->toBe('ready')
        ->and($days[1]->status)->toBe('ready')
        ->and($days[2]->status)->toBe('pending')
        ->and($days[2]->collection_id)->toBeNull()
        ->and($days[2]->generation_attempts)->toBe(0);
});

it('allows only one running plan per learner', function () {
    [$user, $token] = learner();
    profileFor($user, ['native_language' => 'ru']);

    $first = createPlan($this, $token);
    outlinePlan($this, $token, $first['id']);
    $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/plans/{$first['id']}/start")->assertOk();

    $second = createPlan($this, $token);
    outlinePlan($this, $token, $second['id']);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/plans/{$second['id']}/start")
        ->assertStatus(409)
        ->assertJsonPath('code', 'plan_already_active');
});

// ── strictness ────────────────────────────────────────────────────────────────────────────────

it('refuses «убрать из изучения» on a word a running plan is standing on', function () {
    [$user, $token] = learner();
    profileFor($user, ['native_language' => 'ru']);

    $plan = createPlan($this, $token);
    outlinePlan($this, $token, $plan['id']);
    $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/plans/{$plan['id']}/start")->assertOk();

    $collectionId = DB::table('learning_plan_days')->where('plan_id', $plan['id'])->where('day_index', 1)->value('collection_id');
    $termId = DB::table('collection_items')->where('collection_id', $collectionId)->value('term_id');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->deleteJson("/api/v1/pool/terms/{$termId}")
        ->assertStatus(409)
        ->assertJsonPath('code', 'plan_held_term')
        ->assertJsonPath('meta.plan_ids.0', $plan['id']);
});

it('keeps holding through a pause, because pause means «я вернусь»', function () {
    [$user, $token] = learner();
    profileFor($user, ['native_language' => 'ru']);

    $plan = createPlan($this, $token);
    outlinePlan($this, $token, $plan['id']);
    $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/plans/{$plan['id']}/start")->assertOk();
    $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/plans/{$plan['id']}/pause")->assertOk();

    $collectionId = DB::table('learning_plan_days')->where('plan_id', $plan['id'])->where('day_index', 1)->value('collection_id');
    $termId = DB::table('collection_items')->where('collection_id', $collectionId)->value('term_id');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->deleteJson("/api/v1/pool/terms/{$termId}")
        ->assertStatus(409);
});

it('abandoning releases the hold and leaves the words in the pool', function () {
    [$user, $token] = learner();
    profileFor($user, ['native_language' => 'ru']);

    $plan = createPlan($this, $token);
    outlinePlan($this, $token, $plan['id']);
    $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/plans/{$plan['id']}/start")->assertOk();

    $collectionId = DB::table('learning_plan_days')->where('plan_id', $plan['id'])->where('day_index', 1)->value('collection_id');
    $termId = DB::table('collection_items')->where('collection_id', $collectionId)->value('term_id');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/plans/{$plan['id']}/abandon")
        ->assertOk()
        ->assertJsonPath('data.status', 'abandoned');

    $row = DB::table('user_term_progress')->where('user_id', $user->id)->where('term_id', $termId)->first();

    // The reason is gone; the enrolment is NOT. The learner spent days on these words.
    expect(json_decode((string) $row->enrollment_sources, true))->toBe([])
        ->and($row->enrolled_at)->not->toBeNull();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->deleteJson("/api/v1/pool/terms/{$termId}")
        ->assertOk()
        ->assertJsonPath('data.changed', true);
});

it('leaves a word the learner saved by hand studiable after the plan lets go', function () {
    [$user, $token] = learner();
    profileFor($user, ['native_language' => 'ru']);

    $plan = createPlan($this, $token);
    outlinePlan($this, $token, $plan['id']);
    $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/plans/{$plan['id']}/start")->assertOk();

    $collectionId = DB::table('learning_plan_days')->where('plan_id', $plan['id'])->where('day_index', 1)->value('collection_id');
    $termId = DB::table('collection_items')->where('collection_id', $collectionId)->value('term_id');

    // The learner also asks for it by hand: two reasons on one pair.
    $this->withHeader('Authorization', "Bearer {$token}")->putJson("/api/v1/pool/terms/{$termId}")->assertOk();

    $sources = json_decode((string) DB::table('user_term_progress')
        ->where('user_id', $user->id)->where('term_id', $termId)->value('enrollment_sources'), true);
    expect($sources)->toBe(['plan:' . $plan['id'], 'manual']);

    $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/plans/{$plan['id']}/abandon")->assertOk();

    // Releasing the plan takes away the plan's reason and leaves the learner's own.
    $after = json_decode((string) DB::table('user_term_progress')
        ->where('user_id', $user->id)->where('term_id', $termId)->value('enrollment_sources'), true);
    expect($after)->toBe(['manual']);
});

// ── reads ─────────────────────────────────────────────────────────────────────────────────────

it('answers /plans/active with 204 when the learner has none', function () {
    [$user, $token] = learner();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/plans/active')
        ->assertNoContent();
});

it('serves one day with the plan binding lists beside it', function () {
    [$user, $token] = learner();
    profileFor($user, ['native_language' => 'ru']);

    $plan = createPlan($this, $token);
    outlinePlan($this, $token, $plan['id']);

    $day = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/v1/plans/{$plan['id']}/days/1")
        ->assertOk()
        ->json('data');

    expect($day['index'])->toBe(1)
        ->and($day)->toHaveKeys(['entities', 'constraints', 'goal_terms', 'support_lang', 'checkpoints'])
        ->and($day['support_lang'])->toBe('ru');
});

it('reports readiness as a number that cannot flatter — 0 before anything is acquired', function () {
    [$user, $token] = learner();
    profileFor($user, ['native_language' => 'ru']);

    $plan = createPlan($this, $token);
    outlinePlan($this, $token, $plan['id']);
    $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/plans/{$plan['id']}/start")->assertOk();

    $active = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/plans/active')->assertOk()->json('data');

    expect((float) $active['readiness'])->toBe(0.0);
});

it('hides another learner plan behind a 404, not a 403', function () {
    [$owner, $ownerToken] = learner();
    profileFor($owner, ['native_language' => 'ru']);
    [$stranger, $strangerToken] = learner();

    $plan = createPlan($this, $ownerToken);

    // The guard caches the user it resolved for the FIRST request of a test, and every request in
    // one test shares an application instance — so without this the second bearer token is never
    // looked at and the test would pass while proving nothing.
    app('auth')->forgetGuards();

    $this->withHeader('Authorization', "Bearer {$strangerToken}")
        ->getJson("/api/v1/plans/{$plan['id']}")
        ->assertNotFound();
});

// ── the ledger, through the whole flow ────────────────────────────────────────────────────────

it('leaves a ledger row for every paid call the plan made', function () {
    [$user, $token] = learner();
    profileFor($user, ['native_language' => 'ru']);

    $plan = createPlan($this, $token, ['event_date' => now()->addDays(2)->format('Y-m-d')]);
    outlinePlan($this, $token, $plan['id']);
    $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/plans/{$plan['id']}/start")->assertOk();

    $rows = DB::table('generation_requests')->where('plan_id', $plan['id'])->orderBy('created_at')->get();

    // One outline + two days. Every one of them is a call that cost money on the live model, and
    // the PLAN-1a run proved what «recorded only in the request log» is worth.
    expect($rows)->toHaveCount(3)
        ->and($rows->pluck('purpose')->unique()->all())->toBe(['plan'])
        ->and($rows->pluck('user_id')->unique()->all())->toBe([$user->id])
        ->and($rows->pluck('prompt_version')->unique()->all())->toBe(['plan.v0.1.1'])
        ->and($rows[0]->prompt)->toStartWith('outline:')
        ->and($rows[1]->prompt)->toStartWith('day:')
        ->and($rows[1]->size)->toBe(9);
});

it('fails the day loudly when the ledger will not take the row', function () {
    [$user, $token] = learner();
    profileFor($user, ['native_language' => 'ru']);

    $plan = createPlan($this, $token);
    outlinePlan($this, $token, $plan['id']);

    // A ledger that refuses. The day handler catches everything else and turns it into a
    // `fail_reason`; this one must escape, because a day quietly marked «failed» would hide the
    // fact that a call was already paid for.
    app()->instance(\App\Modules\Generation\Application\Port\RecordsPlanSpend::class, new class implements \App\Modules\Generation\Application\Port\RecordsPlanSpend {
        public function record(\App\Modules\Generation\Application\Dto\PlanSpend $spend): void
        {
            throw \App\Modules\Generation\Domain\Exception\PlanSpendNotRecorded::forPlan(
                $spend->planId, $spend->costUsd, new RuntimeException('ledger is down'),
            );
        }
    });
    $model = new FakePlanContentModel();
    $prompts = new PlanPromptLibrary();
    app()->instance(PlanDayComposer::class, new PlanDayComposer(
        $model, $prompts, app(\App\Modules\Generation\Application\Port\RecordsPlanSpend::class),
    ));

    // The queue is sync under test, so the job runs inside the request; without the HTTP kernel's
    // handler in the way, the exception the day generator refused to swallow arrives here.
    $this->withoutExceptionHandling();

    expect(fn () => $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/plans/{$plan['id']}/start"))
        ->toThrow(\App\Modules\Generation\Domain\Exception\PlanSpendNotRecorded::class);

    // …and the day was NOT quietly closed as a product failure.
    $day1 = DB::table('learning_plan_days')->where('plan_id', $plan['id'])->where('day_index', 1)->first();
    expect($day1->status)->not->toBe('ready')
        ->and($day1->fail_reason)->toBeNull();
});

// ── the plan owns its language ────────────────────────────────────────────────────────────────

it('carries the support language on the plan, not on the account', function () {
    [$user, $token] = learner();
    profileFor($user, ['native_language' => 'ru']);

    $plan = createPlan($this, $token);

    expect(DB::table('learning_plans')->where('id', $plan['id'])->value('support_lang'))->toBe('ru')
        ->and($plan['support_lang'])->toBe('ru');
});

it('takes the account language up to the last moment before commitment', function () {
    [$user, $token] = learner();
    profileFor($user, ['native_language' => 'ru']);

    $plan = createPlan($this, $token);

    // Still a draft: the skeleton has not been written yet, so the current answer is the right one.
    profileFor($user, ['native_language' => 'uk']);
    $outlined = outlinePlan($this, $token, $plan['id']);

    expect($outlined['support_lang'])->toBe('uk');
});

it('FREEZES the language at start — the account can move and the plan cannot', function () {
    [$user, $token] = learner();
    profileFor($user, ['native_language' => 'ru']);

    $plan = createPlan($this, $token);
    outlinePlan($this, $token, $plan['id']);
    $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/plans/{$plan['id']}/start")->assertOk();

    // The learner changes their native language mid-plan. The skeleton is already written in
    // Russian, day 1's keys are Russian, and the grading is against them: day 2 arriving in
    // Ukrainian would make one plan claim to be two things.
    profileFor($user, ['native_language' => 'uk']);

    $active = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/plans/active')->assertOk()->json('data');

    expect($active['support_lang'])->toBe('ru')
        ->and(DB::table('learning_plans')->where('id', $plan['id'])->value('support_lang'))->toBe('ru');
});

it('no longer reports recommended_days at all', function () {
    [$user, $token] = learner();
    profileFor($user, ['native_language' => 'ru']);

    $plan = createPlan($this, $token);
    $outlined = outlinePlan($this, $token, $plan['id']);

    // The field was a promise nobody kept: the validator never checked it, and its criterion was
    // passed by making one ability bigger.
    expect($outlined)->not->toHaveKey('recommended_days');
});
