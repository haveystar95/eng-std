<?php

declare(strict_types=1);

use App\Modules\Learning\Application\Dto\PlanSessionTaskView;
use App\Modules\Learning\Domain\Service\SceneDay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * THE WHOLE DAY-SCENE, END TO END, OVER HTTP — план → день → сидение → закрытие.
 *
 * Every other file in this directory asks one question of one class. This one asks the only
 * question the наряд is actually about: does a learner get a scene, walk it, and end the day with
 * the plan one day further on. It is the shape the live run of E2E-SIM-1 walked on a phone, minus
 * the phone: the same endpoints in the same order, and every assertion is something that run had to
 * read out of the database by hand.
 *
 * What it pins, in the order the sitting happens:
 *
 *   THE SHELVES exist as rows, with the tier the server derived from each one;
 *   THE RESCUE KIT is in day 1 and nothing else wrote it there;
 *   THE WARM-UP leads the sitting, and the day's own count excludes it;
 *   «Тебе скажут» is dealt for recognition only, at every stage;
 *   THE DAY CLOSES on the client's own `complete`, and day 2 goes into the queue.
 *
 * Offline: the material comes from {@see \App\Modules\Generation\Infrastructure\Adapter\FakePlanContentModel},
 * so this costs nothing and can run on every commit. The same walk on the LIVE prompts is the
 * report's own run, and it is the one that proves the prompt rather than the machine.
 */
beforeEach(fn () => fakePlanModel());

it('walks a plan from the goal to a closed day, and the day is a scene all the way through', function () {
    [$user, $token] = learner();
    profileFor($user, ['native_language' => 'ru', 'target_language' => 'en']);

    // ── the goal, the skeleton, the commitment ────────────────────────────────────────────────
    $plan = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/plans', [
            'goal_text' => 'Иду к врачу с ребёнком, надо объяснить и понять назначение',
            'target_lang' => 'en',
            'level' => 'basic',
            'event_date' => now()->addDays(6)->format('Y-m-d'),
            'minutes_per_day' => 20,
        ])->assertCreated()->json('data');

    $outlined = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/plans/{$plan['id']}/outline")->assertOk()->json('data');

    // ONE DAY PER SCENE, and the day says what it is about before it says anything else.
    expect($outlined['computed']['intro_days'])->toBe(2)
        ->and($outlined['days'][0]['intro'])->not->toBe('')
        ->and($outlined['days'][0]['term_budget'])->toBe(SceneDay::UNITS);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/plans/{$plan['id']}/start")->assertOk();

    // ── the day, as rows ──────────────────────────────────────────────────────────────────────
    $day1 = DB::table('learning_plan_days')->where('plan_id', $plan['id'])->where('day_index', 1)->first();

    expect($day1->status)->toBe('ready')
        ->and($day1->fail_reason)->toBeNull();

    $shelves = DB::table('terms as t')
        ->join('collection_items as ci', 'ci.term_id', '=', 't.id')
        ->where('ci.collection_id', $day1->collection_id)
        ->get(['t.id', 't.text', 't.shelf', 't.tier', 't.kind', 't.skill_ref', 't.number_value']);

    $bySchelf = $shelves->groupBy('shelf')->map->count()->all();

    expect($bySchelf)->toHaveKeys(['hear', 'say', 'ask', 'words', 'chunks', 'numbers', 'rescue'])
        // The tier is the server's, derived from the shelf and stored beside it (канон §3).
        ->and($shelves->where('shelf', 'hear')->pluck('tier')->unique()->all())->toBe(['understand'])
        ->and($shelves->where('shelf', 'numbers')->pluck('tier')->unique()->all())->toBe(['understand'])
        ->and($shelves->where('shelf', 'say')->pluck('tier')->unique()->all())->toBe(['speak'])
        // Every card of the scene names a skill of it; the kit names none, because it serves the
        // plan and not this situation.
        ->and($shelves->whereNotIn('shelf', ['rescue'])->whereNull('skill_ref'))->toHaveCount(0)
        ->and($shelves->where('shelf', 'rescue')->whereNotNull('skill_ref'))->toHaveCount(0)
        // The digits a number is graded on — the one field of the day that never reaches a screen.
        ->and($shelves->where('shelf', 'numbers')->whereNull('number_value'))->toHaveCount(0);

    // ── the sitting ───────────────────────────────────────────────────────────────────────────
    $session = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/plans/{$plan['id']}/session")->assertOk()->json('data');

    $sections = array_column($session['tasks'], 'section');
    $warmup = array_values(array_filter(
        $session['tasks'],
        static fn (array $t): bool => $t['section'] === PlanSessionTaskView::SECTION_WARMUP,
    ));

    expect($session['strict'])->toBeTrue()
        // THE WARM-UP LEADS, every day, and it is the rescue kit (канон §5).
        ->and($sections[0])->toBe(PlanSessionTaskView::SECTION_WARMUP)
        ->and(array_unique(array_column($warmup, 'shelf')))->toBe(['rescue'])
        // …and it does not enter the day's own count, which is what «N из N» reads.
        ->and($session['day_task_count'])
        ->toBe(count(array_keys($sections, PlanSessionTaskView::SECTION_DAY, true)));

    // «Тебе скажут» is understood and never produced — at every stage, on every card of the shelf.
    foreach ($session['tasks'] as $task) {
        if ($task['tier'] !== 'understand') {
            continue;
        }
        expect($task['card']['exercise_mode'])
            ->toBeIn(['intro', 'multiple_choice', 'listening', 'description_match', 'pick_correct'])
            ->and($task['shelf'])->toBeIn(['hear', 'numbers']);
    }

    // Numbers are stored with the day and dealt by nothing yet — NUM-1 owns the trainer.
    $numbers = $shelves->where('shelf', 'numbers')->pluck('id')->all();
    expect(array_intersect(array_column(array_column($session['tasks'], 'card'), 'term_id'), $numbers))
        ->toBe([]);

    // ── the answers, and the close ────────────────────────────────────────────────────────────
    answerTasks($this, $token, $session);

    // Before the completion nothing has moved — this is the state the live run was stuck in (Д-28).
    expect(DB::table('learning_plan_days')->where('id', $day1->id)->value('status'))->toBe('ready');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/study/sessions/{$session['session_id']}/complete", [
            'ended_at' => now()->toIso8601String(),
        ])->assertOk();

    $statuses = DB::table('learning_plan_days')->where('plan_id', $plan['id'])
        ->orderBy('day_index')->pluck('status', 'day_index')->all();

    // THE DAY CLOSED ON THE CLIENT'S OWN COMPLETE, and day 2 went into the queue behind it.
    expect($statuses[1])->toBe('done')
        ->and($statuses[2])->toBe('ready')
        ->and(DB::table('study_sessions')->where('id', $session['session_id'])->value('ended_at'))
        ->not->toBeNull();

    // …and the plan card can say what the learner did: every card of the scene closed stage A.
    $census = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/v1/plans/{$plan['id']}")->assertOk()->json('data.stage_census');

    expect($census['stage_a_closed'])->toBeGreaterThan(0)
        ->and($census['total'])->toBeGreaterThanOrEqual($census['stage_a_closed']);

    // ── AND NOTHING CLOSED COMES BACK IN THE SAME DAY ─────────────────────────────────────────
    //
    // The gate of the blockers наряд, and the shape of the live failure it closes: on 02.09 the
    // owner answered every card of the scene correctly and the day went on dealing sittings — the
    // five rescue phrases in every one of them, and one card whose intro was never recorded. Every
    // step the next sitting owes must be a step nobody has answered today.
    $next = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/plans/{$plan['id']}/session")->assertOk()->json('data');

    $answeredToday = DB::table('reviews')->where('user_id', $user->id)->pluck('term_id')->all();
    $rescue = $shelves->where('shelf', 'rescue')->pluck('id')->all();
    $repeats = array_values(array_filter(
        $next['tasks'],
        static fn (array $t): bool => in_array($t['card']['term_id'], $answeredToday, true)
            && $t['section'] === PlanSessionTaskView::SECTION_WARMUP,
    ));

    // The next sitting belongs to DAY 2 — the day just walked is closed and does not deal again —
    // and the warm-up does not bring back a phrase this day already answered.
    expect($next['focus_day_index'])->toBe(2)
        ->and($repeats)->toBe([])
        ->and(array_intersect(array_column(array_column($next['tasks'], 'card'), 'term_id'), $rescue))
        ->toBe([]);
});
