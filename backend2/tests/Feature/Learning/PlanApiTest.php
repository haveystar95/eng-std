<?php

declare(strict_types=1);

use App\Modules\Generation\Application\Service\PlanDayComposer;
use App\Modules\Learning\Application\Command\EndPlan;
use App\Modules\Learning\Application\Command\EndPlanHandler;
use App\Modules\Learning\Domain\ValueObject\PlanEnding;
use App\Modules\Learning\Domain\ValueObject\PlanId;
use App\Modules\Shared\Domain\ValueObject\UserId;
use App\Modules\Generation\Infrastructure\Adapter\FakePlanContentModel;
use App\Modules\Generation\Infrastructure\Prompt\PlanPromptLibrary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Modules\Generation\Infrastructure\Job\AttachImagesJob;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use App\Modules\Shared\Domain\Service\Clock;
use Tests\Doubles\FixedClock;

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

it('follows the account when the learner changes their native language (ONB-1)', function () {
    [$user, $token] = learner();
    profileFor($user, ['native_language' => 'ru', 'target_language' => 'en']);

    // The learner edits the one setting the whole product reads…
    $this->withHeader('Authorization', "Bearer {$token}")
        ->putJson('/api/v1/profile', ['native_language' => 'uk'])
        ->assertOk();

    // …and the NEXT plan is explained in it. Nothing on the request says so — the plan never asks.
    expect(createPlan($this, $token)['support_lang'])->toBe('uk');
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
        ->and($plan['computed']['capacity'])->toBe(14)
        ->and($plan['days'])->toHaveCount(3)
        ->and($plan['days'][2]['kind'])->toBe('final')
        ->and($plan['days'][2]['collection_id'])->toBeNull()
        ->and($plan['days'][2]['scheduled_on'])->toBe(now()->addDays(2)->format('Y-m-d'))
        // Every checkpoint of the plan, assembled by the SERVER, on the final day.
        ->and($plan['days'][2]['checkpoints'])->toHaveCount(4)
        ->and($plan['status'])->toBe('draft');
});

it('lays the first day on the learner’s TODAY, not on UTC’s (Д-4)', function () {
    [$user, $token] = learner();
    // Bucharest is UTC+3 in September, so a minute before one in the morning there is still
    // yesterday evening in UTC — the exact hour the live run created its plan at.
    profileFor($user, ['native_language' => 'ru', 'timezone' => 'Europe/Bucharest']);
    $this->app->instance(Clock::class, new FixedClock(new DateTimeImmutable('2026-08-31T21:50:00+00:00')));

    $plan = createPlan($this, $token, ['event_date' => '2026-09-05']);
    $plan = outlinePlan($this, $token, $plan['id']);

    $scheduled = array_column($plan['days'], 'scheduled_on');

    // Preparation starts TODAY in Bucharest — 01.09, not 31.08 — so the four days before the event
    // are the four days that exist, and nothing is left empty in front of the rehearsal.
    expect($scheduled[0])->toBe('2026-09-01')
        ->and(end($scheduled))->toBe('2026-09-05')
        ->and($plan['days'][count($plan['days']) - 1]['kind'])->toBe('final');

    // NOT ONE DAY BEFORE TODAY. That is the whole defect: the live plan's first three teaching days
    // were laid on 31.08–02.09 and one of them was already over when the learner saw it.
    expect(min($scheduled))->toBe('2026-09-01');

    // What is left over is REST, counted and named, rather than a day that fell off the front:
    // teaching days + rest + the rehearsal are exactly the days between today and the event.
    expect($plan['computed']['intro_days'] + $plan['computed']['rest_days'] + 1)
        ->toBe($plan['computed']['max_days'])
        ->and($plan['computed']['max_days'])->toBe(5);
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
        ->and($after['computed']['capacity'])->toBe(24)
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
        ->and(count(json_decode((string) DB::table('learning_plans')->where('id', $plan['id'])->value('outline'), true)['scenes']))
        ->toBe($introBefore - 1);
});

it('writes the abilities as rows, with the day each one landed on', function () {
    // `plan_skills` is the SOURCE and the days are what the scheduler made of it today. A row per
    // ability, in P1's order, carrying the scheduler's two verdicts: which day, and dropped or not.
    [$user, $token] = learner();
    profileFor($user, ['native_language' => 'ru', 'target_language' => 'en']);
    $plan = createPlan($this, $token);
    outlinePlan($this, $token, $plan['id']);

    $rows = DB::table('plan_skills')->where('plan_id', $plan['id'])->orderBy('position')->get();

    expect($rows)->toHaveCount(4)                               // 2 scenes × 2 abilities
        ->and($rows->pluck('position')->all())->toBe([0, 1, 2, 3])
        ->and($rows->pluck('scene_index')->all())->toBe([1, 1, 2, 2])
        ->and($rows->pluck('dropped')->unique()->all())->toBe([false])
        // Every ability is scheduled onto some day, and the days it names exist.
        ->and($rows->pluck('day_index')->filter()->count())->toBe(4)
        // Every row carries the name its day's cards point at — «s1.2», not the ULID of the row,
        // which is regenerated on every reschedule.
        ->and($rows->pluck('skill_ref')->all())->toBe(['s1.1', 's1.2', 's2.1', 's2.2'])
        // The interlocutor, derived from the scene's own opening lines: P1 v0.4 answers with no
        // role object, so there is no name to store and the lines are the whole of it.
        ->and(json_decode((string) $rows[0]->role, true)['opening_lines'])->toHaveCount(2)
        ->and(json_decode((string) $rows[0]->topics, true))->toBe(['область 1']);
});

it('rewrites the abilities when the calendar is recomputed, keeping them the source of the days', function () {
    [$user, $token] = learner();
    profileFor($user, ['native_language' => 'ru', 'target_language' => 'en']);
    $plan = createPlan($this, $token, ['minutes_per_day' => 40]);
    outlinePlan($this, $token, $plan['id']);

    $before = DB::table('plan_skills')->where('plan_id', $plan['id'])->orderBy('position')
        ->pluck('day_index', 'skill_ref')->all();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->patchJson("/api/v1/plans/{$plan['id']}/outline", ['minutes_per_day' => 10])
        ->assertOk();

    $rows = DB::table('plan_skills')->where('plan_id', $plan['id'])->orderBy('position')->get();

    // THE MINUTES NO LONGER MOVE THE DAYS, and that is v0.4 rather than a broken reschedule: a day
    // is a SCENE, so two scenes are two days at ten minutes and at forty. What the minutes decide
    // is how much of a day one SITTING deals. Not one row is duplicated either — A7 REPLACES the
    // set rather than adding to it, which is the half of this test that never changed.
    expect($rows)->toHaveCount(4)
        ->and($rows->pluck('day_index', 'skill_ref')->all())->toBe($before)
        ->and($rows->pluck('skill_ref')->unique())->toHaveCount(4);
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
    // Eighteen cards of the scene plus the five rescue phrases the server writes into day 1.
    expect($termIds)->toHaveCount(23);

    // Strictly enrolled, with the plan named as the reason.
    $sources = DB::table('user_term_progress')
        ->where('user_id', $user->id)
        ->whereIn('term_id', $termIds)
        ->pluck('enrollment_sources');

    expect($sources)->toHaveCount(23);
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
        ->get(['t.is_line', 't.difficulty_score', 't.kind', 't.shelf', 't.tier', 't.frame',
            't.speaker', 't.image_api_prompt']);

    // The day-scene: ten spoken and heard lines, six pieces, two numbers — plus the five rescue
    // phrases, which are lines the server wrote rather than the model.
    expect($terms->where('kind', 'line'))->toHaveCount(15)
        ->and($terms->where('kind', 'chunk'))->toHaveCount(2)
        ->and($terms->where('kind', 'word'))->toHaveCount(4)
        ->and($terms->where('kind', 'number'))->toHaveCount(2)
        ->and($terms->where('is_line', true))->toHaveCount(15)
        // THE TIER, derived from the shelf and written down: the interlocutor's lines and the
        // numbers are understood, everything else is produced (канон §3).
        ->and($terms->where('tier', 'understand')->pluck('shelf')->unique()->sort()->values()->all())
        ->toBe(['hear', 'numbers'])
        ->and($terms->where('tier', 'speak')->pluck('shelf')->unique()->sort()->values()->all())
        ->toBe(['ask', 'chunks', 'rescue', 'say', 'words'])
        // Whose turn it is, and who has no turn at all.
        ->and($terms->where('shelf', 'hear')->where('speaker', 'role'))->toHaveCount(4)
        ->and($terms->where('kind', 'word')->whereNotNull('speaker'))->toHaveCount(0)
        ->and($terms->whereNull('difficulty_score'))->toHaveCount(0);
});

it('gives every card of a plan day a picture to search for, and queues the search', function () {
    // The PLAN-1a defect, both halves. `ImportTerm` was called without `image_api_prompt`, so the
    // image handler had nothing to search on — and nothing dispatched it for a plan anyway, so it
    // never ran. Every day of every plan the owner made came out with no illustration at all, and
    // nothing said so.
    Bus::fake([AttachImagesJob::class]);

    [$user, $token] = learner();
    profileFor($user, ['native_language' => 'ru', 'target_language' => 'en']);
    $plan = createPlan($this, $token);
    outlinePlan($this, $token, $plan['id']);
    $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/plans/{$plan['id']}/start")->assertOk();

    $collectionId = DB::table('learning_plan_days')
        ->where('plan_id', $plan['id'])->where('day_index', 1)->value('collection_id');

    $prompts = DB::table('terms as t')
        ->join('collection_items as ci', 'ci.term_id', '=', 't.id')
        ->where('ci.collection_id', $collectionId)
        ->pluck('t.image_api_prompt');

    // ONLY A WORD IS ILLUSTRATED now (канон §7): a line, a connector and a number carry no picture,
    // and the four words of the day plus the five rescue phrases do. What the PLAN-1a defect was
    // about is unchanged and still asserted — the cards that should have a query have one, and the
    // job that goes looking is dispatched.
    $withPicture = $prompts->filter(static fn (?string $p): bool => $p !== null && trim($p) !== '');

    expect($prompts)->toHaveCount(23)
        ->and($withPicture)->toHaveCount(9);

    Bus::assertDispatched(AttachImagesJob::class);
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

it('abandoning takes the words out of the pool and keeps everything else', function () {
    [$user, $token] = learner();
    profileFor($user, ['native_language' => 'ru']);

    $plan = createPlan($this, $token);
    outlinePlan($this, $token, $plan['id']);
    $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/plans/{$plan['id']}/start")->assertOk();

    $collectionId = DB::table('learning_plan_days')->where('plan_id', $plan['id'])->where('day_index', 1)->value('collection_id');
    $termId = DB::table('collection_items')->where('collection_id', $collectionId)->value('term_id');

    // ANSWERED ONCE — a word the learner really spent time on, which used to be the case an ending
    // KEPT in the pool. It no longer is ({@see PlanTermArchiver}, tested in `PlanTermArchiveTest`).
    answerTimes($this, $token, $termId, 'x', 1);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/plans/{$plan['id']}/abandon")
        ->assertOk()
        ->assertJsonPath('data.status', 'abandoned');

    $row = DB::table('user_term_progress')->where('user_id', $user->id)->where('term_id', $termId)->first();

    // The reason is gone and so is the enrolment: the plan was this pair's only reason to be in the
    // queue, and the plan is over. The RESULTS stay on the row, so adding it back later resumes.
    expect(json_decode((string) $row->enrollment_sources, true))->toBe([])
        ->and($row->enrolled_at)->toBeNull()
        // And `abandon_reason` stays NULL: this ending is the learner's own tap, and «я передумал»
        // needs no column. The tag is only for the endings nobody chose.
        ->and(DB::table('learning_plans')->where('id', $plan['id'])->value('abandon_reason'))->toBeNull();

    // «Убрать из изучения» now has nothing left to do — the ending already did it. It answers, and
    // it answers honestly: `changed: false`, not a 409. The 409 is for a plan that is still RUNNING,
    // and this one is over.
    $this->withHeader('Authorization', "Bearer {$token}")
        ->deleteJson("/api/v1/pool/terms/{$termId}")
        ->assertOk()
        ->assertJsonPath('data.changed', false);

    // The archive itself: the plan row, its days and every answer are still there to read.
    expect(DB::table('learning_plan_days')->where('plan_id', $plan['id'])->count())->toBeGreaterThan(0)
        ->and(DB::table('reviews')->where('term_id', $termId)->count())->toBeGreaterThan(0);
});

it('records WHY when something other than the learner ends the plan', function () {
    // The path a наряд takes: the domain sets the tag, the mapper stores it, and a plan that says
    // «abandoned» can still answer «почему» a month later. Before this the column existed and
    // nothing but a migration's raw UPDATE could write it.
    [$user, $token] = learner();
    profileFor($user, ['native_language' => 'ru']);

    $plan = createPlan($this, $token);
    outlinePlan($this, $token, $plan['id']);

    (app(EndPlanHandler::class))(new EndPlan(
        PlanId::fromString($plan['id']),
        UserId::fromString($user->id),
        PlanEnding::Abandon,
        'prompt_v0_2_1_run',
    ));

    $row = DB::table('learning_plans')->where('id', $plan['id'])->first();

    expect($row->status)->toBe('abandoned')
        ->and($row->abandon_reason)->toBe('prompt_v0_2_1_run');
});

it('serves a day with its register — every word, with the stage it stands on', function () {
    [$user, $token] = learner();
    profileFor($user, ['native_language' => 'ru']);
    $headers = ['Authorization' => "Bearer {$token}"];

    $plan = createPlan($this, $token);
    outlinePlan($this, $token, $plan['id']);
    $this->withHeaders($headers)->postJson("/api/v1/plans/{$plan['id']}/start")->assertOk();

    $day = $this->withHeaders($headers)
        ->getJson("/api/v1/plans/{$plan['id']}/days/1")
        ->assertOk()
        ->json('data');

    expect($day['terms'])->not->toBeEmpty();
    foreach ($day['terms'] as $term) {
        // A word nobody has answered yet stands on stage A, and every row names where it came from
        // — which is what «B · со дня 1» is drawn from on a later day.
        expect($term['stage'])->toBeIn(['a', 'b', 'c'])
            ->and($term['from_day_index'])->toBe(1)
            ->and($term['type'])->toBeString()
            ->and($term['text'])->toBeString();
    }
});

/**
 * PLAN-1c, кадр 08: the plan card sits ABOVE the «сегодня» plate because they are two different
 * piles of work. A word counted in both is the screen asking the learner to do it twice, and the
 * two counters would then disagree about how big the day is.
 */
it('keeps the plan words out of the ordinary day while the plan is running', function () {
    [$user, $token] = learner();
    profileFor($user, ['native_language' => 'ru']);
    $headers = ['Authorization' => "Bearer {$token}"];

    $plan = createPlan($this, $token);
    outlinePlan($this, $token, $plan['id']);
    $this->withHeaders($headers)->postJson("/api/v1/plans/{$plan['id']}/start")->assertOk();

    // The plan HAS enrolled its words — they are in the pool, held by it…
    $held = DB::table('user_term_progress')
        ->where('user_id', $user->id)
        ->whereRaw("enrollment_sources @> ?::jsonb", [json_encode(['plan:' . $plan['id']])])
        ->count();
    expect($held)->toBeGreaterThan(0);

    // …and the ordinary day does not know about a single one of them.
    $home = $this->withHeaders($headers)->getJson('/api/v1/home-plan')->assertOk()->json('data');
    expect($home['in_work']['total'])->toBe(0)
        ->and($home['session']['repeat'] + $home['session']['new'])->toBe(0);

    // Nor does the session the ordinary day would open.
    $session = $this->withHeaders($headers)
        ->postJson('/api/v1/study/sessions', ['session_id' => (string) \Illuminate\Support\Str::ulid(), 'limit' => 20])
        ->assertOk()
        ->json('data');
    expect($session['cards'])->toBe([]);

    // One word ANSWERED — the case «18 слов ушли в общее повторение» (кадр 11) used to be about.
    $answered = DB::table('collection_items')
        ->whereIn('collection_id', DB::table('learning_plan_days')->where('plan_id', $plan['id'])->whereNotNull('collection_id')->pluck('collection_id'))
        ->value('term_id');
    answerTimes($this, $token, (string) $answered, 'x', 1);

    // The plan ends → and the ordinary day is STILL empty. Reversed by the owner on 01.09: an ended
    // plan is an archive and takes its words with it, answered ones included. Nothing is lost — the
    // days, the cards and the whole review log stay readable — and what the learner wants out of it
    // they add to «Учить» themselves.
    $this->withHeaders($headers)->postJson("/api/v1/plans/{$plan['id']}/abandon")->assertOk();

    $after = $this->withHeaders($headers)->getJson('/api/v1/home-plan')->assertOk()->json('data');

    expect($after['in_work']['total'])->toBe(0)
        ->and($after['session']['repeat'] + $after['session']['new'])->toBe(0)
        ->and($held)->toBeGreaterThan(1);
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
        // Two versions and not one: the ledger says which prompt each call actually used rather
        // than stamping both with a single number that would be wrong for one of them the moment
        // they are revised apart.
        ->and($rows->pluck('prompt_version')->unique()->all())->toBe(['plan_outline.v0.4', 'plan_day.v0.4'])
        ->and($rows[0]->prompt)->toStartWith('outline:')
        ->and($rows[1]->prompt)->toStartWith('day:')
        ->and($rows[1]->size)->toBe(\App\Modules\Learning\Domain\Service\SceneDay::UNITS);
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
        $model,
        $prompts,
        app(\App\Modules\Generation\Application\Port\RecordsPlanSpend::class),
        app(\App\Modules\Generation\Application\Port\PlanDefectReporter::class),
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
