<?php

declare(strict_types=1);

use App\Modules\Learning\Infrastructure\Adapter\LoggingModeFallbackReporter;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
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

    // The chain a card is dealt depends on what it IS. A word is met, recognised twice and said;
    // a line is met, recognised once, put together and read aloud — and never typed.
    $kind = DB::table('terms')->where('id', $first)->value('kind');

    expect($chain)->toBe($kind === 'line'
        ? ['intro', 'multiple_choice', 'word_bank', 'speaking']
        : ['intro', 'multiple_choice', 'multiple_choice', 'speaking']);
});

it('deals a line and a word different chains in the same session', function () {
    [, $token, $planId] = startedPlan($this);

    $session = planSession($this, $token, $planId);

    $kinds = DB::table('terms')->pluck('kind', 'id')->all();
    $chains = [];
    foreach ($session['tasks'] as $task) {
        $termId = $task['card']['term_id'];
        $chains[$kinds[$termId] ?? 'word'][$termId][] = $task['card']['exercise_mode'];
    }

    // A LINE is never dealt typing or dictation, at any stage — «нечего печатать по буквам».
    foreach ($chains['line'] ?? [] as $chain) {
        expect($chain)->not->toContain('typing')
            ->and($chain)->not->toContain('dictation')
            ->and($chain[0])->toBe('intro');
    }

    // A WORD gets two recognitions and no assembly step.
    foreach ($chains['word'] ?? [] as $chain) {
        expect(array_slice($chain, 0, 4))->toBe(['intro', 'multiple_choice', 'multiple_choice', 'speaking']);
    }

    expect($chains['line'] ?? [])->not->toBeEmpty()
        ->and($chains['word'] ?? [])->not->toBeEmpty();
});

it('drops the choice card when the day cannot furnish its kind, instead of padding it (Д-2)', function () {
    Cache::forget(LoggingModeFallbackReporter::PLAN_DISTRACTOR_STARVED);

    [, $token, $planId] = startedPlan($this);

    $session = planSession($this, $token, $planId);

    // The day is 8 lines + 4 words + 2 connectors ({@see DayCapacity::split()} on a budget of 14),
    // and `basic` deals three options. A connector therefore needs two OTHER connectors and the day
    // holds one — so a connector gets no choice card at all. Before Д-2 the shortfall was made up
    // from single words, and «which of these is a connector» was answerable by length.
    $kinds = DB::table('terms')->pluck('kind', 'id')->all();
    $byKind = [];
    foreach ($session['tasks'] as $task) {
        $termId = $task['card']['term_id'];
        $byKind[$kinds[$termId] ?? 'none'][] = $task['card']['exercise_mode'];
    }

    expect($byKind['chunk'] ?? [])->not->toBeEmpty()
        ->and($byKind['chunk'])->not->toContain('multiple_choice')
        // The connector is still taught — it is only the CHOICE that cannot be built honestly.
        ->and($byKind['chunk'])->toContain('intro')
        // Words and lines have their own kind to spare, so nothing else lost a card.
        ->and($byKind['word'])->toContain('multiple_choice')
        ->and($byKind['line'])->toContain('multiple_choice');

    // …and the fact is COUNTED, because a session that is quietly shorter is exactly the kind of
    // thing nobody notices for months.
    expect(Cache::get(LoggingModeFallbackReporter::PLAN_DISTRACTOR_STARVED))->toBeGreaterThan(0);
});

it('deals the interlocutor’s own line for recognition only, and says whose it is (Д-8)', function () {
    [, $token, $planId] = startedPlan($this);

    // One of the day's lines is the OTHER person's turn — «Hello. What seems to be the problem with
    // your child?» in the live run. It is in the day so the learner will understand it when it is
    // said to them, and it is the one card of a plan they are never asked to say.
    $roleLine = DB::table('terms')->where('kind', 'line')->orderBy('id')->value('id');
    DB::table('terms')->where('id', $roleLine)->update(['speaker' => 'role']);
    $learnerLine = DB::table('terms')->where('kind', 'line')->where('id', '!=', $roleLine)->orderBy('id')->value('id');
    DB::table('terms')->whereIn('kind', ['line'])->where('id', '!=', $roleLine)->update(['speaker' => 'learner']);

    $session = planSession($this, $token, $planId);

    $modes = [];
    $speakers = [];
    foreach ($session['tasks'] as $task) {
        $modes[$task['card']['term_id']][] = $task['card']['exercise_mode'];
        $speakers[$task['card']['term_id']] = $task['speaker'];
    }

    // Meeting it and choosing its meaning stay. Assembling it, typing it and reading it aloud do
    // not: the live run spent a word bank making the learner build the doctor's question word by
    // word, and then a speaking card making them read it out.
    expect($modes[$roleLine])->toContain('intro')
        ->and($modes[$roleLine])->toContain('multiple_choice')
        ->and($modes[$roleLine])->not->toContain('word_bank')
        ->and($modes[$roleLine])->not->toContain('scramble')
        ->and($modes[$roleLine])->not->toContain('typing')
        ->and($modes[$roleLine])->not->toContain('speaking');

    // The learner's OWN lines are untouched — this is about whose turn it is, not about lines.
    expect($modes[$learnerLine])->toContain('speaking');

    // …and the card SAYS whose line it is, in both directions. A recognition card that did not
    // would be indistinguishable from one the learner is expected to produce.
    expect($speakers[$roleLine])->toBe('role')
        ->and($speakers[$learnerLine])->toBe('learner');

    // The DAY screen carries it too, so the register does not read as «eleven sentences you are
    // learning to say» with the doctor's among them.
    $dayTerms = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/v1/plans/{$planId}/days/1")
        ->assertOk()
        ->json('data.terms');

    $byId = array_column($dayTerms, 'speaker', 'id');
    expect($byId[$roleLine])->toBe('role')
        ->and($byId[$learnerLine])->toBe('learner');
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

    // A night passes and the words move to stage B — but the PLANNER still decides when each of
    // them comes back. Whatever the session carries as `plan_review` is a word SM-2 has made due;
    // the plan never pulls one forward, which is the whole «чем ≠ когда» split.
    //
    // (Under v0.1 this step could assert the stronger «nothing at all», because a stage A of five
    // cards pushed every word past a single night. v0.2's checklists are shorter — a word gets four
    // cards, a line four — so some of them are legitimately due the next morning. The assertion
    // moved to the rule rather than to the arithmetic that happened to follow from it.)
    ageHistory($user->id, days: 1);
    $tomorrow = planSession($this, $token, $planId);
    // «Owed a card», which is the planner's own predicate and not a narrower hand-rolled one: due
    // now, OR never scheduled at all. The second half is not a loophole — a pair still on the
    // recognition rungs has no `due_at` because those rungs never schedule (DECISIONS п. 203), and
    // «незаконченное» is the most urgent thing the trainer has. What the plan may never do is pull
    // forward a word the planner has scheduled for LATER, and that is what this asserts.
    $notOwed = DB::table('user_term_progress')
        ->where('user_id', $user->id)
        ->where('due_at', '>', now())
        ->pluck('term_id')
        ->all();

    foreach ($tomorrow['tasks'] as $task) {
        if ($task['source'] === 'plan_review') {
            expect($task['card']['term_id'])->not->toBeIn($notOwed);
        }
    }

    // Now the planner says they are due — and the plan deals them their stage-B checklist as the
    // SEAM, after the day's own material. (Under PLAN-FIX-3 the revision moved behind the day: it is
    // a section the learner reads a label over — «Повторение · из прошлых дней» — and a section
    // announced after the cards it labels is not a section. The budget agrees, since the day is what
    // must not be cut.)
    ageHistory($user->id, days: 7);
    $later = planSession($this, $token, $planId);

    $review = array_values(array_filter(
        $later['tasks'],
        static fn (array $t): bool => $t['section'] === 'review',
    ));
    $first = $later['tasks'][0] ?? null;
    $firstReview = $review[0] ?? null;

    expect($later['tasks'])->not->toBeEmpty()
        // The day leads, and a first meeting is stage A.
        ->and($first['source'])->toBe('new')
        ->and($first['stage'])->toBe('a')
        ->and($first['from_day_index'])->toBe(2)
        // …and day 1's words follow it, on stage B, carried in as the seam.
        ->and($firstReview)->not->toBeNull()
        ->and($firstReview['stage'])->toBe('b')
        ->and($firstReview['source'])->toBe('plan_review')
        ->and($firstReview['from_day_index'])->toBe(1)
        ->and($firstReview['card']['term_id'])->toBeIn($day1Terms);
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
    // A goal big enough for five introduction days, and ten calendar days to teach them in: past
    // the eager threshold, so the plan pays for one day and stops. The SIZE has to come from the
    // goal now — P1 v0.2 is not told the calendar, so a later event date no longer buys more days.
    [, $token, $planId] = startedPlan($this, [
        'goal_text' => 'Большая цель [scenes:5]',
        'event_date' => now()->addDays(10)->format('Y-m-d'),
    ]);

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

it('closes the day off the CLIENT’S complete, and queues day 2 from that alone (Д-1)', function () {
    // THE WHOLE CHAIN, in one test, because it was broken in the join and not in any of its parts:
    // client → `POST /study/sessions/{id}/complete` → `CompleteStudySession` → `PlanDayPassing` →
    // `PlanGenerationPolicy::nextAfterDone` → day 2 written.
    //
    // Every piece of that worked in isolation and the live run still ended with day 1 `ready` and
    // day 2 never queued, because the app never sent the completion (`session_screen.dart` called
    // `record` only from the ordinary summary). The day turned `done` on the NEXT session build —
    // the very fallback PLAN-SESSION-FIX declared closed — which is why this test must not build a
    // second session to prove anything.
    [, $token, $planId] = startedPlan($this, [
        'goal_text' => 'Большая цель [scenes:5]',
        'event_date' => now()->addDays(10)->format('Y-m-d'),
    ]);

    $statuses = fn (): array => DB::table('learning_plan_days')
        ->where('plan_id', $planId)->orderBy('day_index')->pluck('status', 'day_index')->all();

    expect($statuses()[1])->toBe('ready')
        ->and($statuses()[2])->toBe('pending');

    // ONE sitting: build it, answer every task, and close it the way the app does.
    $session = planSession($this, $token, $planId);
    answerTasks($this, $token, $session);

    // …and before the completion, nothing has moved. This is the state the live run was stuck in.
    expect($statuses()[1])->toBe('ready')
        ->and($statuses()[2])->toBe('pending')
        ->and(DB::table('study_sessions')->where('id', $session['session_id'])->value('ended_at'))
        ->toBeNull();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/study/sessions/{$session['session_id']}/complete")
        ->assertOk();

    // The completion alone did all three things.
    expect(DB::table('study_sessions')->where('id', $session['session_id'])->value('ended_at'))
        ->not->toBeNull();
    expect($statuses()[1])->toBe('done')
        ->and($statuses()[2])->toBe('ready');
});

it('builds a day on demand, idempotently, and refuses to run more than two ahead', function () {
    [, $token, $planId] = startedPlan($this, [
        'goal_text' => 'Большая цель [scenes:5]',
        'event_date' => now()->addDays(10)->format('Y-m-d'),
    ]);

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
