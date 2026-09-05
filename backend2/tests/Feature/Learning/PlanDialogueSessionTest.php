<?php

declare(strict_types=1);

use App\Modules\Learning\Domain\Service\PlanSessionSections as S;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * THE SITTING CARRIES THE CONVERSATION — наряд DAY-2, Ч.1.4 and Ч.1.5.
 *
 * Three claims, and they are the whole of what the client is promised:
 *
 *   the parts of a sitting arrive as CODES the client localises — `warmup`, `words`,
 *   `dialogue_intro`, `dialogue` — rather than as a shelf the client has to interpret;
 *   the day a scene is introduced has no conversation in it (канон §10: «в одной посадке карточка
 *   не проходит обе ступени»), and the next day does;
 *   the conversation is WHOLE and in the order the scene is spoken, so the screen can play it from
 *   its first line instead of from whatever is owed today.
 */
beforeEach(function (): void {
    fakePlanModel();
    DB::table('learning_mode_settings')->where('scope', 'global')->whereNull('user_id')->update(['enabled' => true]);
});

/** The task codes of a sitting, each run of the same code collapsed to one entry. */
function sectionRuns(array $session): array
{
    $out = [];
    foreach ($session['tasks'] as $task) {
        if ($out === [] || end($out) !== $task['section_code']) {
            $out[] = $task['section_code'];
        }
    }

    return $out;
}

/**
 * The parts of a sitting as «this code, of this scene» — the key a присест is cut on.
 *
 * A sitting can hold two scenes, and each of them walks канон §10's order once: today's pieces and
 * introduction, then yesterday's pieces and its conversation. So `words` legitimately appears twice
 * — for two different scenes — and what must never repeat is a part OF ONE SCENE.
 */
function sectionRunKeys(array $session): array
{
    $out = [];
    foreach ($session['tasks'] as $task) {
        $key = $task['section_code'] === S::WARMUP
            ? S::WARMUP
            : $task['section_code'] . '#' . ($task['from_day_index'] ?? 0);
        if ($out === [] || end($out) !== $key) {
            $out[] = $key;
        }
    }

    return $out;
}

/** Do ONE scene's parts arrive in канон §10's order inside the sitting? */
function orderedWithinScene(array $session, int $dayIndex): bool
{
    $ranks = [];
    foreach ($session['tasks'] as $task) {
        if ($task['section_code'] === S::WARMUP || ($task['from_day_index'] ?? 0) !== $dayIndex) {
            continue;
        }
        $rank = S::rankOf($task['section_code']);
        if ($ranks === [] || end($ranks) !== $rank) {
            $ranks[] = $rank;
        }
    }

    $sorted = $ranks;
    sort($sorted);

    return $ranks === $sorted;
}

it('opens a scene with the pieces, the introduction — and the conversation, the same day', function () {
    [, $token, $planId] = startedPlan($this, ['event_date' => now()->addDays(10)->format('Y-m-d')]);

    $session = planSession($this, $token, $planId);

    // THE SCENE IS SPOKEN THE DAY IT IS MET (DAY-FIX-2, DECISIONS п. 266): the introduction first,
    // then the conversation, in one sitting — «гейт B без A» still holds, A simply closes on the
    // intro and B opens behind it the same day.
    expect(sectionRuns($session))->toBe([S::WARMUP, S::WORDS, S::DIALOGUE_INTRO, S::DIALOGUE])
        ->and($session['dialogues'])->toHaveCount(1)
        ->and($session['dialogues'][0]['day_index'])->toBe(1);
});

it('plays yesterday’s scene as a conversation, whole and in order', function () {
    [$user, $token, $planId] = startedPlan($this, ['event_date' => now()->addDays(10)->format('Y-m-d')]);

    walkDay($this, $token, $planId, 1);
    ageHistory($user->id, days: 1);

    $session = planSession($this, $token, $planId);
    expect($session['day_index'])->toBe(2);

    // TWO CONVERSATIONS — scene 2's own (met and spoken today, DAY-FIX-2) and scene 1's, back in
    // the seam by assembly. The one under test is yesterday's.
    expect($session['dialogues'])->toHaveCount(2);
    $dialogue = collect($session['dialogues'])->firstWhere('day_index', 1);
    expect($dialogue)->not->toBeNull();
    expect($dialogue['day_index'])->toBe(1)
        ->and($dialogue['scene_title'])->not->toBeEmpty()
        ->and($dialogue['turns'])->not->toBeEmpty();

    // IT ALTERNATES, every turn names a side, and a role turn is always a `hear` card.
    $previous = null;
    foreach ($dialogue['turns'] as $turn) {
        expect($turn['turn'])->toBeIn(['role', 'you'])
            ->and($turn['text'])->not->toBeEmpty()
            ->and($turn['turn'] === 'role' ? $turn['shelf'] : 'hear')->toBe('hear');
        expect($turn['turn'])->not->toBe($previous);
        $previous = $turn['turn'];
    }

    // THE SITTING'S OWN PART FOR IT — the three shelves at stage B are one section, «диалог сцены»,
    // and it is what took the place of three separately-captioned situational cards.
    $keys = sectionRunKeys($session);
    expect(sectionRuns($session))->toContain(S::DIALOGUE)
        // Each part of each scene appears ONCE — a part that comes back is a part that was
        // interleaved, and «Диалог» announced twice over one conversation is a lie about it.
        ->and($keys)->toBe(array_values(array_unique($keys)))
        // TODAY'S SCENE, THEN THE SEAM (PLAN-FIX-7), and each of them in канон §10's own order.
        ->and(orderedWithinScene($session, 2))->toBeTrue()
        ->and(orderedWithinScene($session, 1))->toBeTrue()
        ->and(array_search(S::DIALOGUE_INTRO, sectionRuns($session), true))
        ->toBeLessThan(array_search(S::DIALOGUE, sectionRuns($session), true));

    // THE CONVERSATION IS WHOLE and the sitting is not: the screen plays every turn and hands the
    // learner a move only where a task with the same `term_id` exists.
    $dialogueTasks = array_values(array_filter(
        $session['tasks'],
        static fn (array $t): bool => $t['section_code'] === S::DIALOGUE && ($t['from_day_index'] ?? null) === 1,
    ));
    $turnIds = array_column($dialogue['turns'], 'term_id');
    foreach ($dialogueTasks as $task) {
        expect($turnIds)->toContain($task['card']['term_id'])
            ->and($task['from_day_index'])->toBe(1);
    }

    // …and the tasks are dealt IN THE CONVERSATION'S ORDER, which is the point of shipping it.
    $positions = array_map(
        static fn (array $t): int => (int) array_search($t['card']['term_id'], $turnIds, true),
        $dialogueTasks,
    );
    $sorted = $positions;
    sort($sorted);
    expect($positions)->toBe($sorted);
});

it('cuts a присест between parts, so no exchange is ever split in half', function () {
    [$user, $token, $planId] = startedPlan($this, [
        'event_date' => now()->addDays(10)->format('Y-m-d'),
        'minutes_per_day' => 10,
    ]);

    walkDay($this, $token, $planId, 1);
    ageHistory($user->id, days: 1);

    $session = planSession($this, $token, $planId);
    expect(array_sum($session['sittings']))->toBe(count($session['tasks']));

    $keyOf = static fn (array $t): string => $t['section_code'] === S::WARMUP
        ? S::WARMUP
        : $t['section_code'] . '#' . ($t['from_day_index'] ?? 0);

    $at = 0;
    foreach (array_slice($session['sittings'], 0, -1) as $size) {
        $at += $size;
        // Every break is a change of part — so a break can never land inside `dialogue`, and
        // therefore never between the two turns of one exchange.
        expect($keyOf($session['tasks'][$at - 1]))->not->toBe($keyOf($session['tasks'][$at]));
    }
});

it('builds a conversation for a day written before the prompt wrote one', function () {
    // «Ни один существующий план не ломается»: the days of every plan started on P2 v0.4 have
    // shelves and no chain, and the server pairs them by `skill_ref` instead.
    [$user, $token, $planId] = startedPlan($this, ['event_date' => now()->addDays(10)->format('Y-m-d')]);

    walkDay($this, $token, $planId, 1);
    ageHistory($user->id, days: 1);

    // The day as v0.4 left it: material, no chain.
    DB::table('learning_plan_days')->where('plan_id', $planId)->update(['dialogue' => null]);

    $session = planSession($this, $token, $planId);

    $yesterday = collect($session['dialogues'])->firstWhere('day_index', 1);
    expect($yesterday)->not->toBeNull()
        ->and($yesterday['turns'])->not->toBeEmpty()
        ->and(sectionRuns($session))->toContain(S::DIALOGUE);
});
