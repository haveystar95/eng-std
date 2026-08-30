<?php

declare(strict_types=1);

use App\Modules\Shared\Domain\ValueObject\Ulid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * `POST /plans/{id}/days/{n}/session` — the plan being STUDIED.
 *
 * Everything here runs on the offline plan model, so the material is real (it passes both
 * validators) and free. What is being tested is not the material but the mechanism: which trainer a
 * word is owed, in what order the day deals them, what closes a stage, when the focus moves, and
 * what a day opened out of turn gets instead.
 */
beforeEach(function (): void {
    // Offline, and resolved back to prove it — see fakePlanModel() in tests/Pest.php.
    fakePlanModel();

    // The plan ladder deals `intro` and `speaking`, and both ship DARK — a new trainer is switched
    // on себе → бете → всем, never by a migration. That release rule is not what these tests are
    // about, so the owner's switch is thrown here: without it the stage-A checklist is three steps
    // and this file would be quietly testing a narrower ladder than the one it describes.
    DB::table('learning_mode_settings')->where('scope', 'global')->whereNull('user_id')->update(['enabled' => true]);
});

/** A started plan: draft → outline → start, with day 1 generated inside the request. */
function startedPlan(object $ctx, array $overrides = []): array
{
    [$user, $token] = learner();
    profileFor($user, ['native_language' => 'ru', 'target_language' => 'en']);

    $plan = $ctx->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/plans', [
            'goal_text' => 'Иду к врачу, болит спина, надо объяснить и понять назначение',
            'target_lang' => 'en',
            'level' => 'basic',
            'event_date' => now()->addDays(2)->format('Y-m-d'),
            'minutes_per_day' => 20,
            ...$overrides,
        ])
        ->assertCreated()
        ->json('data');

    $ctx->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/plans/{$plan['id']}/outline")
        ->assertOk();
    $ctx->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/plans/{$plan['id']}/start")
        ->assertOk();

    return [$user, $token, $plan['id']];
}

function planSession(object $ctx, string $token, string $planId, ?int $dayIndex = null): array
{
    $url = $dayIndex === null
        ? "/api/v1/plans/{$planId}/session"
        : "/api/v1/plans/{$planId}/days/{$dayIndex}/session";

    return $ctx->withHeader('Authorization', "Bearer {$token}")->postJson($url)->assertOk()->json('data');
}

/** Answer every task of a session correctly, in the order it was dealt. */
function answerTasks(object $ctx, string $token, array $session, int $seq = 1, ?string $at = null): int
{
    $reviews = [];
    $exposures = [];

    foreach ($session['tasks'] as $task) {
        $card = $task['card'];
        if ($card['exercise_mode'] === 'intro') {
            $exposures[] = ['term_id' => $card['term_id'], 'shown_at' => $at ?? now()->toIso8601String()];

            continue;
        }
        $reviews[] = [
            'id' => (string) Ulid::generate(),
            'term_id' => $card['term_id'],
            'exercise_mode' => $card['exercise_mode'],
            'response' => $card['answer'],
            'answered_at' => $at ?? now()->toIso8601String(),
            'client_seq' => $seq++,
            'session_id' => $session['session_id'],
            'ladder_step' => $card['ladder_step'],
        ];
    }

    $ctx->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/reviews/batch', ['reviews' => $reviews, 'exposures' => $exposures])
        ->assertOk();

    return $seq;
}

/**
 * PUSH THE LEARNER'S HISTORY BACK `$days` DAYS — the same trick `qa:time-travel` plays on a real
 * account, and the reason a plan test uses it instead of moving the clock.
 *
 * The server reads {@see Clock}, whose `SystemClock` builds a plain `DateTimeImmutable` that
 * `Carbon::setTestNow` never reaches; binding a `FixedClock` mid-test works for a container the test
 * itself resolves from and did not reach the handler here. Ageing the ROWS is the honest inversion:
 * «a night has passed» and «this word was answered a week ago» are statements about stored data, and
 * moving the data is both simpler and closer to what actually happens to a learner.
 */
function ageHistory(string $userId, int $days): void
{
    $shift = "INTERVAL '{$days} days'";
    DB::statement("UPDATE reviews SET answered_at = answered_at - {$shift}, created_at = created_at - {$shift} WHERE user_id = ?", [$userId]);
    DB::statement("UPDATE term_exposures SET shown_at = shown_at - {$shift} WHERE user_id = ?", [$userId]);
    DB::statement("UPDATE user_term_progress SET due_at = due_at - {$shift}, last_reviewed_at = last_reviewed_at - {$shift} WHERE user_id = ?", [$userId]);
}

/**
 * Play day `$dayIndex` until the focus leaves it — the session's card budget can be smaller than the
 * day's whole checklist, so «пройти день» is more than one sitting and the test has to say so.
 */
function walkDay(object $ctx, string $token, string $planId, int $dayIndex, int $seq = 1): int
{
    for ($i = 0; $i < 8; $i++) {
        $session = planSession($ctx, $token, $planId);
        if ($session['focus_day_index'] !== $dayIndex || $session['tasks'] === []) {
            break;
        }
        $seq = answerTasks($ctx, $token, $session, $seq);
    }

    return $seq;
}

// ── the shape of a strict session ─────────────────────────────────────────────────────────────

it('deals day 1 as stage A: intro first, then the two recognitions, the word bank and speaking', function () {
    [, $token, $planId] = startedPlan($this);

    $session = planSession($this, $token, $planId);

    expect($session['strict'])->toBeTrue()
        ->and($session['day_index'])->toBe(1)
        ->and($session['focus_day_index'])->toBe(1)
        ->and($session['tasks'])->not->toBeEmpty();

    // Every task of a never-studied day is stage A, and belongs to day 1.
    foreach ($session['tasks'] as $task) {
        expect($task['stage'])->toBe('a')
            ->and($task['source'])->toBe('new')
            ->and($task['from_day_index'])->toBe(1);
    }

    // The first word's own chain, in the stage's fixed order.
    $first = $session['tasks'][0]['card']['term_id'];
    $chain = [];
    foreach ($session['tasks'] as $task) {
        if ($task['card']['term_id'] === $first) {
            $chain[] = $task['card']['exercise_mode'];
        }
    }

    expect($chain)->toBe(['intro', 'multiple_choice', 'multiple_choice', 'word_bank', 'speaking']);
});

it('carries the level’s knobs, and says which of them the card actually honoured', function () {
    [, $token, $planId] = startedPlan($this, ['level' => 'zero']);

    $session = planSession($this, $token, $planId);

    expect($session['knobs'])->toBe([
        'mc_options' => 3,
        'distractor_closeness' => 'far',
        'cloze_blanks' => 1,
        'bank_extra' => 0,
        'typing_hint' => 'first_letter',
        'tts_rate' => 'slow',
    ]);

    $mc = null;
    foreach ($session['tasks'] as $task) {
        if ($task['card']['exercise_mode'] === 'multiple_choice') {
            $mc = $task;

            break;
        }
    }

    expect($mc)->not->toBeNull()
        ->and($mc['knobs_applied'])->toBe(['mc_options', 'distractor_closeness'])
        ->and($mc['knobs_ignored'])->toBe([])
        // `mc_options: 3` at level `zero` — the knob that reaches the trainer.
        ->and(count($mc['card']['options']))->toBeLessThanOrEqual(3);
});

it('names the speaking card’s form per stage, so the client knows what to put on screen', function () {
    [, $token, $planId] = startedPlan($this);

    $session = planSession($this, $token, $planId);

    $speaking = array_values(array_filter(
        $session['tasks'],
        static fn (array $t): bool => $t['card']['exercise_mode'] === 'speaking',
    ));

    expect($speaking)->not->toBeEmpty()
        // Stage A: the word is on the screen and the learner reads it aloud.
        ->and($speaking[0]['speaking_form'])->toBe('word_on_screen');
});

// ── the day passing, and the focus moving ─────────────────────────────────────────────────────

it('passes the day when every word closes stage A, and moves the focus to day 2', function () {
    [, $token, $planId] = startedPlan($this);

    walkDay($this, $token, $planId, 1);

    $after = planSession($this, $token, $planId);

    expect($after['focus_day_index'])->toBe(2)
        ->and(DB::table('learning_plan_days')->where('plan_id', $planId)->where('day_index', 1)->value('status'))
        ->toBe('done');
});

/**
 * The handover from stage A to stage B — and the invariant that constrains it.
 *
 * The plan says WHICH card; the repetition planner says WHEN a word comes back. So a word that
 * closed stage A does not reappear the next morning merely because a night has passed: it reappears
 * when SM-2 makes it DUE, and the plan then deals it the stage-B checklist. The night is a
 * necessary condition for the stage to advance and never a sufficient one to summon the word — and
 * this test asserts both halves, because getting the second one wrong is how «чем» would quietly
 * start deciding «когда».
 */
it('opens stage B when the planner makes the word due again — the night alone does not summon it', function () {
    [$user, $token, $planId] = startedPlan($this, ['event_date' => now()->addDays(10)->format('Y-m-d')]);

    walkDay($this, $token, $planId, 1);

    $day1Terms = DB::table('collection_items')
        ->where('collection_id', DB::table('learning_plan_days')->where('plan_id', $planId)->where('day_index', 1)->value('collection_id'))
        ->pluck('term_id')
        ->all();

    // Same day: day 1's words owe nothing at all, so nothing of theirs is dealt.
    $sameDay = planSession($this, $token, $planId, 2);
    $stageB = array_filter($sameDay['tasks'] ?? [], static fn (array $t): bool => ($t['stage'] ?? null) === 'b');
    expect($stageB)->toBe([]);

    // A night passes, and NOTHING else: the words are on stage B now, but SM-2 has them scheduled
    // days out, so the session still carries none of them. The plan does not pull a word forward.
    ageHistory($user->id, days: 1);
    $tomorrow = planSession($this, $token, $planId);
    expect(array_filter($tomorrow['tasks'], static fn (array $t): bool => $t['source'] === 'plan_review'))->toBe([]);

    // Now the planner says they are due — and the plan deals them their stage-B checklist FIRST,
    // ahead of the new material, because warming up on what you know comes before meeting what you
    // do not.
    ageHistory($user->id, days: 7);
    $later = planSession($this, $token, $planId);

    $first = $later['tasks'][0] ?? null;

    expect($later['tasks'])->not->toBeEmpty()
        ->and($first['stage'])->toBe('b')
        ->and($first['source'])->toBe('plan_review')
        ->and($first['from_day_index'])->toBe(1)
        ->and($first['card']['term_id'])->toBeIn($day1Terms)
        // …and the day-2 words that follow are stage A, as a first meeting must be.
        ->and(array_values(array_filter(
            $later['tasks'],
            static fn (array $t): bool => $t['source'] === 'new',
        ))[0]['stage'] ?? null)->toBe('a');
});

// ── what the plan screen reads ────────────────────────────────────────────────────────────────

it('reports the focus, the next day, the days to the event and «ты уже можешь»', function () {
    [, $token, $planId] = startedPlan($this, ['event_date' => now()->addDays(4)->format('Y-m-d')]);

    $plan = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/plans/active')->assertOk()->json('data');

    expect($plan['focus_day_index'])->toBe(1)
        ->and($plan['next_day_index'])->toBe(2)
        ->and($plan['days_to_event'])->toBe(4)
        ->and($plan['deadline_tight'])->toBeFalse()
        // Nothing studied yet: no word has reached stage C, and no conversation has confirmed a
        // checkpoint. Both halves of the formula are zero and the number says so.
        ->and($plan['readiness'])->toBe(0)
        ->and($plan['can_already'])->not->toBeEmpty();

    foreach ($plan['can_already'] as $line) {
        expect($line['hit'])->toBeFalse()
            ->and($line['text'])->toBeString()
            ->and($line['day_index'])->toBeInt();
    }
});

it('moves the reported focus as days are passed', function () {
    [, $token, $planId] = startedPlan($this);

    walkDay($this, $token, $planId, 1);

    $plan = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/v1/plans/{$planId}")->assertOk()->json('data');

    // Two introduction days on a three-day plan, so once the focus is on the second there is no
    // «next» — the thing after it is the final day, which teaches nothing.
    expect($plan['focus_day_index'])->toBe(2)
        ->and($plan['next_day_index'])->toBeNull();
});

// ── when a plan spends money ──────────────────────────────────────────────────────────────────

/**
 * A short plan arrives whole — one day at a time, never side by side.
 *
 * The sequence is the assertion. Day n is written FROM days 1…n−1: its terms go into the prompt's
 * KNOWN block so the model gives them fresh examples in the new situation instead of teaching them
 * again. The first version of the eager branch dispatched every day at once, and the live S1 run
 * showed the cost — day 2's call finished before day 1's collection existed, its KNOWN block went
 * out empty, and not one of day 1's nine terms got its day-2 example. Nothing failed; the material
 * was simply written as if the previous day had not happened.
 */
it('writes a short plan whole, but strictly one day at a time', function () {
    [, $token, $planId] = startedPlan($this);

    $collections = DB::table('learning_plan_days')->where('plan_id', $planId)
        ->whereNotNull('collection_id')->orderBy('day_index')->pluck('collection_id', 'day_index')->all();

    expect($collections)->toHaveCount(2);

    // Day 2's material was written while day 1 already existed — so day 1's terms were in the
    // KNOWN block, and day 2 introduces none of them.
    $terms = fn (int $day): array => DB::table('collection_items')
        ->where('collection_id', $collections[$day])->pluck('term_id')->all();

    expect(array_intersect($terms(1), $terms(2)))->toBe([]);

    // The proof that the ORDER held: the day-2 collection is younger than every term of day 1.
    $day2CreatedAt = DB::table('collections')->where('id', $collections[2])->value('created_at');
    $lastDay1Term = DB::table('terms')->whereIn('id', $terms(1))->max('created_at');

    expect($day2CreatedAt)->toBeGreaterThanOrEqual($lastDay1Term);
});

it('writes only day 1 of a LONG plan at the start, and the next when a day is walked', function () {
    // Ten introduction days: past the eager threshold, so the plan pays for one day and stops.
    [, $token, $planId] = startedPlan($this, ['event_date' => now()->addDays(10)->format('Y-m-d')]);

    $statuses = fn (): array => DB::table('learning_plan_days')
        ->where('plan_id', $planId)->orderBy('day_index')->pluck('status', 'day_index')->all();

    $before = $statuses();
    expect($before[1])->toBe('ready')
        ->and($before[2])->toBe('pending')
        ->and(count(array_filter($before, static fn (string $s): bool => $s === 'ready')))->toBe(1);

    // Day 1 walked → day 2 queued. «Done», not «ready»: the evidence that the learner will come
    // back is that they came back.
    walkDay($this, $token, $planId, 1);
    $after = $statuses();

    expect($after[1])->toBe('done')
        ->and($after[2])->toBe('ready');
});

it('builds a day on demand, idempotently, and refuses to run more than two ahead', function () {
    [, $token, $planId] = startedPlan($this, ['event_date' => now()->addDays(10)->format('Y-m-d')]);

    $generate = fn (int $n) => $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/plans/{$planId}/days/{$n}/generate");

    // Two days ahead of the focus (day 1) is what the ceiling allows.
    expect($generate(2)->assertOk()->json('data.status'))->toBe('ready')
        ->and($generate(3)->assertOk()->json('data.status'))->toBe('ready')
        // Calling it again on a day that is already written is not an error and does not pay twice.
        ->and($generate(2)->assertOk()->json('data.status'))->toBe('ready');

    $attempts = DB::table('learning_plan_days')->where('plan_id', $planId)->where('day_index', 2)->value('generation_attempts');
    expect($attempts)->toBe(1);

    // The third is over the ceiling: a 409 with a sentence, so the screen can say why.
    $generate(4)->assertStatus(409)->assertJsonPath('code', 'plan_day_capped');

    expect(DB::table('learning_plan_days')->where('plan_id', $planId)->where('day_index', 4)->value('status'))
        ->toBe('pending');
});

// ── a day opened out of turn ──────────────────────────────────────────────────────────────────

it('gives a day opened ahead of the focus a SOFT session — no stages, no crediting', function () {
    [$user, $token, $planId] = startedPlan($this);

    // Day 2 is not generated yet, so open day 1 from a focus that has moved past it instead: the
    // rule is «not the focus → soft», and day 2 ahead of the focus is the same rule.
    $session = planSession($this, $token, $planId, 2);

    expect($session['strict'])->toBeFalse()
        ->and($session['focus_day_index'])->toBe(1);

    foreach ($session['tasks'] as $task) {
        expect($task['stage'])->toBeNull()
            ->and($task['source'])->toBe('soft');
    }

    // Soft = practice: the session row says so, so nothing it produces schedules or credits.
    expect(DB::table('study_sessions')->where('id', $session['session_id'])->value('is_practice'))->toBeTruthy();
});

it('answers `scope=plan` on the ordinary session path with the plan’s own day', function () {
    [, $token, $planId] = startedPlan($this);

    $body = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/study/sessions', ['scope' => 'plan'])
        ->assertOk()
        ->json('data');

    expect($body['plan_id'])->toBe($planId)
        ->and($body['strict'])->toBeTrue()
        ->and($body['day_index'])->toBe(1);
});

it('falls back to the ordinary session when `scope=plan` and no plan is running', function () {
    [$user, $token] = learner();
    profileFor($user, ['native_language' => 'ru']);

    $body = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/study/sessions', ['scope' => 'plan'])
        ->assertOk()
        ->json('data');

    // The ordinary payload, not the plan one: `cards`, no `plan_id`.
    expect($body)->toHaveKey('cards')
        ->and($body)->not->toHaveKey('plan_id');
});

it('404s a plan that belongs to somebody else', function () {
    [, , $planId] = startedPlan($this);
    [, $other] = learner();

    // The guard caches the user it resolved for the FIRST request of a test, and every request in
    // one test shares an application instance — without this the second bearer token is never
    // looked at and the test would pass while proving nothing.
    app('auth')->forgetGuards();

    $this->withHeader('Authorization', "Bearer {$other}")
        ->postJson("/api/v1/plans/{$planId}/session")
        ->assertStatus(404);
});
