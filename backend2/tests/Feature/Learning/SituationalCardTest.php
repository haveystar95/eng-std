<?php

declare(strict_types=1);

use App\Modules\Learning\Domain\Service\PlanSessionSections as S;
use App\Modules\Learning\Domain\ValueObject\SituationalSituation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * THE SCENE'S OWN TRAINER, IN A LIVE DAY — наряд SIT-1, end to end.
 *
 * One mechanic on three shelves: a position on the support language, options, a tap. This file
 * judges it the way the learner meets it — through the plan session endpoint, on a day the fake
 * model actually wrote — because everything that makes the card what it is (which shelf owes it,
 * what its situation is assembled from, where it falls in the sitting) is a fact about a DAY and
 * cannot be seen in a unit test of a card.
 */
beforeEach(function (): void {
    fakePlanModel();
    DB::table('learning_mode_settings')->where('scope', 'global')->whereNull('user_id')->update(['enabled' => true]);
});

/** A learner one night past day 1, so day 1's cards stand on stage B — where the situation lives. */
function situationalFixture(object $ctx): array
{
    [$user, $token] = learner();
    profileFor($user, ['native_language' => 'ru', 'target_language' => 'en']);

    $planId = startedPlanFor($ctx, $token, ['event_date' => now()->addDays(10)->format('Y-m-d')]);
    $seq = walkDay($ctx, $token, $planId, 1);
    ageHistory($user->id, days: 1);

    return ['user' => $user, 'token' => $token, 'plan' => $planId, 'seq' => $seq];
}

/** Every task of a session dealt on one of the three situational trainers. */
function situationalTasks(array $session): array
{
    return array_values(array_filter(
        $session['tasks'],
        static fn (array $t): bool => str_starts_with((string) $t['card']['exercise_mode'], 'situational_'),
    ));
}

it('deals each shelf its own situational trainer at stage B, and nothing else at B', function () {
    $f = situationalFixture($this);

    // Day 1's own cards come back as the seam of day 2, standing on stage B.
    $session = planSession($this, $f['token'], $f['plan']);
    $byMode = [];
    foreach ($session['tasks'] as $task) {
        if ($task['stage'] !== 'b') {
            continue;
        }
        $byMode[$task['shelf'] ?? '—'][] = $task['card']['exercise_mode'];
    }

    expect($byMode)->not->toBeEmpty();
    foreach (['say' => 'situational_say', 'ask' => 'situational_ask', 'hear' => 'situational_hear'] as $shelf => $mode) {
        expect($byMode[$shelf] ?? [])->not->toBeEmpty("no stage-B card on the «{$shelf}» shelf")
            // ЗАМЕЩАЕТ, НЕ ДОПОЛНЯЕТ: stage B of these three shelves is the situational card and
            // nothing beside it, so the day does not grow.
            ->and(array_unique($byMode[$shelf]))->toBe([$mode]);
    }
});

it('puts the понимаю card on its second touch and never asks it to be produced', function () {
    $f = situationalFixture($this);
    $session = planSession($this, $f['token'], $f['plan']);

    $hear = array_values(array_filter(
        $session['tasks'],
        static fn (array $t): bool => ($t['shelf'] ?? null) === 'hear' && $t['stage'] === 'b',
    ));

    expect($hear)->not->toBeEmpty();
    foreach ($hear as $task) {
        expect($task['tier'])->toBe('understand')
            ->and($task['card']['exercise_mode'])->toBe('situational_hear')
            // Graded by the tapped option's id: the options ARE translations, and no translation
            // ever enters a text answer key.
            ->and($task['card']['answer'])->toBe($task['card']['term_id'])
            ->and($task['card']['option_ids'])->toHaveCount(count($task['card']['options']))
            // The learner never says the interlocutor's line back.
            ->and($task['speaks_after_choice'])->toBeFalse();
    }
});

it('says the chosen line aloud on the two speak shelves, and grades only the tap', function () {
    $f = situationalFixture($this);
    $session = planSession($this, $f['token'], $f['plan']);

    $spoken = array_values(array_filter(
        situationalTasks($session),
        static fn (array $t): bool => in_array($t['shelf'] ?? null, ['say', 'ask'], true),
    ));

    expect($spoken)->not->toBeEmpty();
    foreach ($spoken as $task) {
        expect($task['speaks_after_choice'])->toBeTrue()
            ->and($task['tier'])->toBe('speak')
            // The answer is the line itself, graded as text like any other choice card.
            ->and($task['card']['answer'])->toBeString()
            ->and($task['card']['option_ids'])->toBeNull();

        // …and WHAT is on the card depends on the strictness of the turn (наряд SCENE-RUN, Ч.1):
        // the choice offers lines, the assembly offers the blocks they are built from. Both grade
        // the same text, which is why they are one trainer and one checklist step.
        if ($task['turn_level'] === 'assemble') {
            expect($task['card']['options'])->toBeNull()
                ->and($task['card']['chips'])->toContain(...explode(' ', (string) $task['card']['answer']));

            continue;
        }
        expect($task['card']['options'])->toContain($task['card']['answer']);
    }
});

it('never puts the answer’s translation on the card, on any of the three', function () {
    // THE ONE THING A SITUATION MUST NOT BE. A prompt that glosses the right option turns стуация
    // into a translation exercise (канон §4, §13) — and the guarantee is by construction: the
    // translation is not an input to the situation builder at all.
    $f = situationalFixture($this);
    $session = planSession($this, $f['token'], $f['plan']);

    $tasks = situationalTasks($session);
    expect($tasks)->not->toBeEmpty();

    foreach ($tasks as $task) {
        $translation = (string) DB::table('term_translations')
            ->where('term_id', $task['card']['term_id'])->where('is_primary', true)->value('text');

        $printed = implode(' | ', array_filter([
            $task['card']['prompt'],
            $task['situation']['context'] ?? null,
            $task['situation']['task'] ?? null,
            $task['situation']['role_line'] ?? null,
        ]));

        expect($translation)->not->toBe('')
            ->and($printed)->not->toContain($translation);
    }
});

it('assembles the situation out of the day, by one of the two routes and no other', function () {
    $f = situationalFixture($this);
    $session = planSession($this, $f['token'], $f['plan']);

    $sources = [];
    foreach (situationalTasks($session) as $task) {
        expect($task['situation'])->toBeArray();
        $sources[] = $task['situation']['source'];

        if ($task['card']['exercise_mode'] === 'situational_hear') {
            // «Что вы сейчас услышите», named by the scene.
            expect($task['situation']['source'])->toBe(SituationalSituation::SOURCE_SCENE)
                ->and($task['situation']['context'])->toBeString();

            continue;
        }

        expect($task['situation']['source'])->toBeIn([
            SituationalSituation::SOURCE_ROLE_LINE,
            SituationalSituation::SOURCE_SKILL,
        ]);
        if ($task['situation']['source'] === SituationalSituation::SOURCE_ROLE_LINE) {
            expect($task['situation']['role_line'])->toBeString()
                ->and($task['situation']['role_line_term_id'])->toBeString();
        }
    }

    // The paired route is the ordinary one on a day whose shelves share their abilities — if it ever
    // stops being reached at all, the fallback has quietly become the whole feature.
    expect($sources)->toContain(SituationalSituation::SOURCE_ROLE_LINE);
});

it('grades a tapped meaning by ID, and never drops it as a stale ladder answer', function () {
    // THE RISKIEST PATH IN THIS НАРЯД. A card whose correct option is a translation must be graded by
    // the tapped option's id, and the ONE other card that works that way is guarded by the rung —
    // which is exactly why this one must not be. `situational_hear` is dealt at stage B of a scene,
    // where the pair is usually graduated, and a rung-1 claim from a graduated pair is DROPPED as a
    // stale ladder answer. Read off the rung, every second touch of the понимаю tier would have
    // vanished from the log without a single test going red.
    $f = situationalFixture($this);
    $session = planSession($this, $f['token'], $f['plan']);

    $hear = array_values(array_filter(
        $session['tasks'],
        static fn (array $t): bool => $t['card']['exercise_mode'] === 'situational_hear',
    ));
    expect($hear)->not->toBeEmpty();

    $task = $hear[0];
    $before = DB::table('reviews')->where('user_id', $f['user']->id)->count();

    $this->withHeader('Authorization', "Bearer {$f['token']}")
        ->postJson('/api/v1/reviews/batch', ['reviews' => [[
            'id' => (string) \App\Modules\Shared\Domain\ValueObject\Ulid::generate(),
            'term_id' => $task['card']['term_id'],
            'exercise_mode' => 'situational_hear',
            // The learner taps the right meaning; the client uploads that option's TERM ID.
            'response' => $task['card']['term_id'],
            'answered_at' => now()->toIso8601String(),
            'client_seq' => $f['seq'] + 1,
            'session_id' => $session['session_id'],
            'ladder_step' => $task['card']['ladder_step'],
        ]]])
        ->assertOk();

    $review = DB::table('reviews')
        ->where('user_id', $f['user']->id)
        ->where('term_id', $task['card']['term_id'])
        ->where('exercise_mode', 'situational_hear')
        ->orderByDesc('created_at')
        ->first();

    // The row exists — it was not dropped — and the tap was graded CORRECT rather than compared as
    // text against the term's own forms, which is what would have failed a right answer.
    expect(DB::table('reviews')->where('user_id', $f['user']->id)->count())->toBe($before + 1)
        ->and($review)->not->toBeNull()
        ->and($review->grade)->not->toBe('again');
});

it('does not deal a situational card in a session that has no scene', function () {
    // Free practice and the ordinary session: no scene, no situation, nothing honest to ask.
    [$user, $token] = learner();
    profileFor($user, ['native_language' => 'ru', 'target_language' => 'en']);
    seedWordFor($user, 'apple', 'яблоко');

    $session = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/study/sessions', ['size' => 10])->assertOk()->json('data');

    foreach ($session['cards'] as $card) {
        expect($card['exercise_mode'])->not->toStartWith('situational_');
    }
});

// ── Ч-3: the order of the sitting ────────────────────────────────────────────────────────────

it('deals the day in the canon’s order: warm-up, pieces, meeting the scene’s lines', function () {
    [$user, $token] = learner();
    profileFor($user, ['native_language' => 'ru', 'target_language' => 'en']);
    $planId = startedPlanFor($this, $token, ['event_date' => now()->addDays(10)->format('Y-m-d')]);

    $session = planSession($this, $token, $planId);

    // THE SERVER NAMES THE PART, and the client no longer derives it from the shelf (наряд DAY-2,
    // Ч.1.4). It cannot: «Ты ответишь» at stage A is the introduction and the same card at stage B
    // is the conversation, and `shelf` says `say` for both.
    $seen = [];
    foreach ($session['tasks'] as $task) {
        if ($seen === [] || end($seen) !== $task['section_code']) {
            $seen[] = $task['section_code'];
        }
    }

    // Each part appears ONCE — a part that comes back is a part that was interleaved.
    expect($seen)->toBe(array_values(array_unique($seen)));

    // …and in the canon's own order (§10).
    $ranks = array_map(S::rankOf(...), $seen);
    $sorted = $ranks;
    sort($sorted);
    expect($ranks)->toBe($sorted)
        // Day 1 of a plan: the kit, the pieces, meeting the scene's lines — and the conversation
        // behind them, the same day (DAY-FIX-2, DECISIONS п. 266 — the scene is spoken the day it
        // is met; the intro still comes first).
        ->and($seen)->toBe([S::WARMUP, S::WORDS, S::DIALOGUE_INTRO, S::DIALOGUE])
        ->and($session['dialogues'])->toHaveCount(1);
});

// ── Ч-6: присесты ────────────────────────────────────────────────────────────────────────────

it('deals the WHOLE day whatever the minutes, and says where the sittings break', function () {
    // Ч-6: THE MINUTES ARE A ПРИСЕСТ, NOT A LIMIT ON THE DAY. Until this наряд the budget cut the
    // task list — ten minutes dealt a shorter lesson than forty, and the tail came back rebuilt from
    // scratch. Both learners now get the same day; what differs is where it is honest to stop.
    //
    // ONE learner and one plan, read twice with the minutes changed underneath: the same day, the
    // same cards, the same ladder — so any difference between the two payloads is the budget's and
    // nothing else's.
    [$user, $token] = learner();
    profileFor($user, ['native_language' => 'ru', 'target_language' => 'en']);
    $planId = startedPlanFor($this, $token, [
        'event_date' => now()->addDays(10)->format('Y-m-d'),
        'minutes_per_day' => 10,
    ]);

    $short = planSession($this, $token, $planId);
    DB::table('learning_plans')->where('id', $planId)->update(['minutes_per_day' => 40]);
    $long = planSession($this, $token, $planId);

    expect(count($short['tasks']))->toBe(count($long['tasks']))
        ->and(count($short['tasks']))->toBeGreaterThan(0)
        ->and(count($short['sittings']))->toBeGreaterThanOrEqual(count($long['sittings']));

    $session = $short;

    expect($session['sittings'])->not->toBeEmpty()
        // Nothing burns: the parts add up to the whole day.
        ->and(array_sum($session['sittings']))->toBe(count($session['tasks']));

    // Every break falls where the part changes — and a part is «this code, of this scene», because
    // one sitting can hold two scenes' conversations.
    $keyOf = static fn (array $t): string => $t['section_code'] === S::WARMUP
        ? S::WARMUP
        : $t['section_code'] . '#' . ($t['from_day_index'] ?? 0);
    $at = 0;
    foreach (array_slice($session['sittings'], 0, -1) as $size) {
        $at += $size;
        expect($keyOf($session['tasks'][$at - 1]))->not->toBe($keyOf($session['tasks'][$at]));
    }
});

// ── Ч-4: разогрев v2 ─────────────────────────────────────────────────────────────────────────

it('carries yesterday’s misses into the warm-up, one light touch each, capped at five', function () {
    [$user, $token] = learner();
    profileFor($user, ['native_language' => 'ru', 'target_language' => 'en']);
    $planId = startedPlanFor($this, $token, ['event_date' => now()->addDays(10)->format('Y-m-d')]);

    $first = planSession($this, $token, $planId);
    // Answer day 1 wrong, everywhere — «непослушный» день целиком, so the cap has something to bite.
    answerTasksWrong($this, $token, $first);
    ageHistory($user->id, days: 1);

    $session = planSession($this, $token, $planId);
    $warmup = array_values(array_filter(
        $session['tasks'],
        static fn (array $t): bool => $t['section'] === 'warmup',
    ));
    $misses = array_values(array_filter($warmup, static fn (array $t): bool => $t['source'] === 'warmup_miss'));

    expect($misses)->not->toBeEmpty()
        // Пять, не больше: разогрев — две минуты, и он не растёт от плохого вечера.
        ->and(count($misses))->toBeLessThanOrEqual(5);

    foreach ($misses as $task) {
        // ONE LIGHT TOUCH, and not the stage's whole checklist: no stage, no ordinal, a recognition
        // card.
        expect($task['stage'])->toBeNull()
            ->and($task['of_steps'])->toBe(0)
            ->and($task['card']['exercise_mode'])->toBe('multiple_choice');
    }

    // …and they come BEFORE the day, like the rescue kit they stand beside.
    $lastWarmup = array_key_last($warmup);
    expect($session['tasks'][$lastWarmup]['section'])->toBe('warmup');
});

it('keeps TODAY’s misses out of TODAY’s warm-up', function () {
    // DECISIONS п. 238: a card missed twenty minutes ago is already coming back at the end of this
    // посадка and in tomorrow's warm-up. A third helping in the same evening is a punishment.
    [$user, $token] = learner();
    profileFor($user, ['native_language' => 'ru', 'target_language' => 'en']);
    $planId = startedPlanFor($this, $token, ['event_date' => now()->addDays(10)->format('Y-m-d')]);

    $first = planSession($this, $token, $planId);
    answerTasksWrong($this, $token, $first);

    // No night in between: the same local day.
    $again = planSession($this, $token, $planId);
    $misses = array_filter($again['tasks'], static fn (array $t): bool => $t['source'] === 'warmup_miss');

    expect($misses)->toBe([]);
});

it('brings a reply missed in yesterday’s conversation back as today’s dialogue turn, not as a warm-up choice', function () {
    // The live day 2 of 07.09 (наряд DAY-FIX-3): three replies missed in the dialogue came back the
    // next morning as translation choices in the warm-up, that choice was the line's one show of
    // the day, and the dialogue never dealt them — the day stood at «почти» with nothing left to
    // deal. A line past its introduction is the conversation's to touch.
    [$user, $token] = learner();
    profileFor($user, ['native_language' => 'ru', 'target_language' => 'en']);
    $planId = startedPlanFor($this, $token, ['event_date' => now()->addDays(10)->format('Y-m-d')]);

    $first = planSession($this, $token, $planId);
    $missed = null;
    foreach ($first['tasks'] as $task) {
        if ($task['stage'] === 'b' && $task['card']['exercise_mode'] === 'situational_say') {
            $missed = $task;

            break;
        }
    }
    expect($missed)->not->toBeNull();
    $termId = $missed['card']['term_id'];

    $rest = array_values(array_filter(
        $first['tasks'],
        static fn (array $t): bool => $t['card']['term_id'] !== $termId || $t['card']['exercise_mode'] !== 'situational_say' || $t['stage'] !== 'b',
    ));
    $seq = answerTasks($this, $token, ['session_id' => $first['session_id'], 'tasks' => $rest]);
    answerTasksWrong($this, $token, ['session_id' => $first['session_id'], 'tasks' => [$missed]], $seq);
    ageHistory($user->id, days: 1);

    $session = planSession($this, $token, $planId);
    $ofLine = array_values(array_filter(
        $session['tasks'],
        static fn (array $t): bool => $t['card']['term_id'] === $termId,
    ));

    expect($ofLine)->not->toBeEmpty()
        ->and(array_column($ofLine, 'source'))->not->toContain('warmup_miss')
        ->and(array_column(array_column($ofLine, 'card'), 'exercise_mode'))->toContain('situational_say');

    // …and the day closes on that turn — the line was the one thing holding it open.
    answerTasks($this, $token, $session);
    $day = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/v1/plans/{$planId}/days/1")->assertOk()->json('data');
    expect($day['day_state'])->toBe('done');
});

/** Answer every gradable task of a session WRONG, in the order it was dealt. */
function answerTasksWrong(object $ctx, string $token, array $session, int $seq = 1): int
{
    $reviews = [];
    foreach ($session['tasks'] as $task) {
        $card = $task['card'];
        if ($card['exercise_mode'] === 'intro') {
            continue;
        }
        $reviews[] = [
            'id' => (string) \App\Modules\Shared\Domain\ValueObject\Ulid::generate(),
            'term_id' => $card['term_id'],
            'exercise_mode' => $card['exercise_mode'],
            'response' => 'решительно не тот ответ',
            'answered_at' => now()->toIso8601String(),
            'client_seq' => $seq++,
            'session_id' => $session['session_id'],
            'ladder_step' => $card['ladder_step'],
        ];
    }

    $ctx->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/reviews/batch', ['reviews' => $reviews])
        ->assertOk();

    return $seq;
}
