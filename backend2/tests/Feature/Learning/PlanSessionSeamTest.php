<?php

declare(strict_types=1);

use App\Modules\Learning\Application\Dto\PlanSessionTaskView;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * THERE IS NO TOP-UP BESIDE A PLAN DAY — and this file is what is left of the one there was.
 *
 * A plan session used to deal its day and then top the sitting up from the learner's ordinary queue,
 * «so a plan does not suspend the rest of their vocabulary». Two live runs cost that idea its
 * credibility: French cards inside an `ru→en` lesson (31.08), and then, with a language-pair filter
 * already in place, «паспорт» out of an abandoned holiday plan dealt inside a lesson about renting a
 * flat (01.09). The bucket is gone — not filtered, not called — and what may be in a plan's sitting
 * is settled by the read itself ({@see \Tests\Feature\Learning\PlanSessionScopeTest}).
 *
 * The fixtures below build exactly what the top-up used to reach for: the learner's own words, in
 * the plan's own pair, overdue. Each test is now the statement that none of it arrives.
 *
 * The rest of the file is the OTHER half of the same incident and is unchanged: the client counted
 * the whole sitting as the day and said «День 1 пройден · 21 фраза и слово» over a day of fourteen,
 * and a walked day stayed `ready` until somebody opened the next session.
 */
beforeEach(function (): void {
    fakePlanModel();
    DB::table('learning_mode_settings')->where('scope', 'global')->whereNull('user_id')->update(['enabled' => true]);
});

/**
 * A term in `$lang`, in a collection of `$support` → `$lang`, enrolled in this learner's pool and
 * due since yesterday.
 *
 * The COLLECTION is what makes this a pair rather than a language: the support side of a card is
 * read off the collection the term is shown through (DECISIONS п. 81), so a term with no collection
 * would fall back to the profile and the test would be measuring the fallback.
 */
function duePoolTerm(object $user, string $lang, string $support, string $text, string $translation): string
{
    $termId = Ulid::generate();
    DB::table('terms')->insert([
        'id' => $termId, 'lang' => $lang, 'text' => $text, 'normalized_text' => mb_strtolower($text),
        'type' => 'phrase', 'source' => 'ai', 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('term_translations')->insert([
        'id' => Ulid::generate(), 'term_id' => $termId, 'lang' => $support, 'text' => $translation,
        'is_primary' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $collectionId = Ulid::generate();
    DB::table('collections')->insert([
        'id' => $collectionId, 'owner_id' => $user->id, 'type' => 'custom', 'source' => 'user',
        'title' => "Папка {$lang}", 'topic' => null, 'source_lang' => $support, 'target_lang' => $lang,
        'visibility' => 'private', 'items_count' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('collection_items')->insert([
        'id' => Ulid::generate(), 'collection_id' => $collectionId, 'term_id' => $termId,
        'position' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);

    DB::table('user_term_progress')->insert([
        'user_id' => $user->id, 'term_id' => $termId,
        'state' => 'review', 'acquisition' => 'graduated', 'learning_step' => 0,
        'reps' => 2, 'successful_reviews' => 2, 'lapses' => 0,
        'ease_factor' => 2.5, 'interval_days' => 3, 'due_at' => now()->subDay(),
        'enrolled_at' => now()->subDays(2), 'enrollment_sources' => json_encode(['manual']),
        'created_at' => now()->subDays(2), 'updated_at' => now(),
    ]);

    return $termId;
}

it('tops a plan day up with nothing at all — not even a word of its own pair', function () {
    [$user, $token, $planId] = startedPlan($this);

    // Two words due beside the plan. The French one was the 31.08 incident; the English one is the
    // learner's own word, in the plan's own pair, and used to be exactly what the bucket was FOR.
    // Neither is dealt now, and that is the whole change: the rule is «only this plan's cards», not
    // «this plan's cards plus whatever else looks close enough».
    $french = duePoolTerm($user, 'fr', 'ru', 'Je voudrais enregistrer mon bagage.', 'Я хотел бы сдать багаж.');
    $english = duePoolTerm($user, 'en', 'ru', 'boarding pass', 'посадочный талон');

    $session = planSession($this, $token, $planId);

    $dealt = array_column(array_column($session['tasks'], 'card'), 'term_id');

    expect($dealt)->not->toContain($french)
        ->and($dealt)->not->toContain($english)
        ->and($session['tasks'])->not->toBeEmpty();
});

it('keeps a foreign term out of the option pool as well, not only out of the running order', function () {
    // The French pair was hydrated alongside the day's own content, which made it a candidate
    // DISTRACTOR on an English card even before it was dealt as a task of its own.
    [$user, $token, $planId] = startedPlan($this);
    $french = duePoolTerm($user, 'fr', 'ru', 'la carte d’embarquement', 'посадочный талон');

    $session = planSession($this, $token, $planId);

    foreach ($session['tasks'] as $task) {
        expect($task['card']['option_ids'] ?? [])->not->toContain($french);
    }
});

it('puts every task of the day before every task of the seam, and says where the seam is', function () {
    [$user, $token, $planId] = startedPlan($this, ['event_date' => now()->addDays(10)->format('Y-m-d')]);
    duePoolTerm($user, 'en', 'ru', 'boarding pass', 'посадочный талон');

    // Day 1 walked, so day 2 has an earlier day to revise. On day 1 there is no seam at all, which
    // is the other half of the contract and is asserted in the next test.
    walkDay($this, $token, $planId, 1);
    ageHistory($user->id, days: 8);

    $session = planSession($this, $token, $planId);
    $sections = array_column($session['tasks'], 'section');

    // THREE SECTIONS, in one order and never interleaved: the warm-up the plan opens every sitting
    // with (канон §5), then the day, then the revision of the days before it. `day_task_count` is a
    // COUNT of the middle one and not an index into the list — the warm-up stands before it.
    $warmup = array_keys($sections, PlanSessionTaskView::SECTION_WARMUP, true);
    // The scene run is «part of the day» whichever scene it runs, and it is dealt LAST, behind the
    // seam (SCENE-RUN; DAY-FIX-2 orders it after everything) — so it is not part of this contiguity.
    $dayTasks = array_values(array_filter(
        array_keys($sections, PlanSessionTaskView::SECTION_DAY, true),
        static fn (int $i): bool => $session['tasks'][$i]['section_code'] !== 'scene_run',
    ));
    $review = array_keys($sections, PlanSessionTaskView::SECTION_REVIEW, true);

    expect($session)->toHaveKey('day_task_count')
        ->and($warmup)->not->toBeEmpty()
        ->and($dayTasks)->not->toBeEmpty()
        ->and($review)->not->toBeEmpty()
        // Each section is one contiguous run, in this order — that is what makes a caption over it
        // a caption rather than a label on scattered cards.
        ->and(max($warmup))->toBeLessThan(min($dayTasks));

    // …WITHIN EACH SITTING (наряд DAY-FIX-3, Ч.4): the seam's words stand in «Материал» behind the
    // day's own words, the seam's lines in «Разговор» behind the day's own dialogue. Across the
    // two the day and the seam alternate by design — material first, whichever day it is from.
    $cut = $session['sittings'][0];
    foreach ([[0, $cut], [$cut, count($session['tasks'])]] as [$from, $to]) {
        $dayPart = array_filter($dayTasks, static fn (int $i): bool => $i >= $from && $i < $to);
        $reviewPart = array_filter($review, static fn (int $i): bool => $i >= $from && $i < $to);
        if ($dayPart !== [] && $reviewPart !== []) {
            expect(max($dayPart))->toBeLessThan(min($reviewPart));
        }
    }
    expect($session)
        // `day_task_count` counts the whole day part — the run of yesterday's scene included.
        ->and($session['day_task_count'])->toBe(count($dayTasks) + count(array_filter(
            $session['tasks'],
            static fn (array $t): bool => $t['section_code'] === 'scene_run',
        )))
        ->and($session['day_task_count'])->toBeLessThan(count($session['tasks']));

    // And `section` agrees with the day each card was introduced on — which is what it now MEANS.
    // It used to read «`from_day_index` is null», the top-up's signature; with the top-up gone that
    // test would have made the seam permanently empty.
    foreach ($session['tasks'] as $task) {
        if ($task['section'] === PlanSessionTaskView::SECTION_WARMUP) {
            // The kit lives on day 1 and comes back every morning after it, so «which day» says
            // nothing about which section it is in — the shelf does.
            expect($task['shelf'])->toBe('rescue')->and($task['origin'])->toBeNull();

            continue;
        }
        // THE SCENE RUN is part of the DAY whichever scene it runs (SCENE-RUN: «часть дня, а не
        // шва»), so its day says nothing about its section either.
        if ($task['section_code'] === 'scene_run') {
            expect($task['section'])->toBe(PlanSessionTaskView::SECTION_DAY);

            continue;
        }

        expect($task['section'])->toBe(
            $task['from_day_index'] === $session['day_index']
                ? PlanSessionTaskView::SECTION_DAY
                : PlanSessionTaskView::SECTION_REVIEW,
        )->and($task['origin'])->toBeNull();
    }
});

it('lays a seamed sitting out as words, connectors, replies, and only then the seam', function () {
    // PLAN-FIX-4, the стык: the day's own blocks in order, the whole checklist of each card inside
    // its block, and «Повторение» after all of it — with `day_task_count` naming the boundary.
    [$user, $token, $planId] = startedPlan($this, [
        'level' => 'conversational',
        'event_date' => now()->addDays(10)->format('Y-m-d'),
    ]);

    walkDay($this, $token, $planId, 1);
    ageHistory($user->id, days: 8);

    $session = planSession($this, $token, $planId);
    $kinds = DB::table('terms')->pluck('kind', 'id')->all();
    $rank = ['word' => 0, 'chunk' => 1, 'line' => 2];

    $dayCount = $session['day_task_count'];
    expect($dayCount)->toBeLessThan(count($session['tasks']));

    $section = static fn (string $name): array => array_values(array_filter(
        $session['tasks'],
        static fn (array $t): bool => $t['section'] === $name,
    ));

    // 0. The warm-up leads, and it is the rescue kit and nothing else.
    expect($section(PlanSessionTaskView::SECTION_WARMUP))->not->toBeEmpty()
        ->and(array_unique(array_column($section(PlanSessionTaskView::SECTION_WARMUP), 'shelf')))
        ->toBe(['rescue']);

    // 1. The day's own tasks, in block order.
    $last = -1;
    $seen = [];
    $dayTasks = $section(PlanSessionTaskView::SECTION_DAY);
    foreach ($dayTasks as $task) {
        $kind = $kinds[$task['card']['term_id']] ?? 'word';
        $seen[$kind] = true;
        expect($rank[$kind])->toBeGreaterThanOrEqual($last);
        $last = $rank[$kind];
    }
    expect($seen)->toHaveKeys(['word', 'chunk', 'line'])
        ->and($dayTasks)->toHaveCount($dayCount)
        // The day opens on a first meeting of a piece — not on the sentence built out of it.
        ->and($dayTasks[0]['card']['exercise_mode'])->toBe('intro')
        ->and($kinds[$dayTasks[0]['card']['term_id']])->toBe('word');

    // 2. The seam, after every one of them, and nothing of the day inside it.
    foreach ($section(PlanSessionTaskView::SECTION_REVIEW) as $task) {
        expect($task['section'])->toBe(PlanSessionTaskView::SECTION_REVIEW)
            ->and($task['from_day_index'])->toBeLessThan($session['day_index']);
    }
});

it('counts the day out of its own material when nothing else is due', function () {
    [, $token, $planId] = startedPlan($this);

    $session = planSession($this, $token, $planId);

    $sections = array_column($session['tasks'], 'section');

    // Nothing is due and no earlier day exists, so the sitting is the WARM-UP and the day, and
    // nothing else. The count is the day's own cards — which is what «N из N» on the day screen
    // reads, and why the five rescue phrases coming back every morning must not enter it.
    expect(array_values(array_unique($sections)))
        ->toBe([PlanSessionTaskView::SECTION_WARMUP, PlanSessionTaskView::SECTION_DAY])
        ->and($session['day_task_count'])
        ->toBe(count(array_keys($sections, PlanSessionTaskView::SECTION_DAY, true)))
        ->and($session['day_task_count'])->toBeLessThan(count($session['tasks']));
});

// ── the day is judged when the sitting ENDS ───────────────────────────────────────────────────

it('marks the day passed as soon as its session is completed, without building another one', function () {
    // The live shape of the complaint: sixty-three exercises answered, «День 1 пройден» on the
    // client, and a plan card that still said «День 1 из 5». The verdict was derived correctly and
    // only ever WRITTEN while assembling the next session, so nobody had asked for it yet.
    [, $token, $planId] = startedPlan($this);

    $session = planSession($this, $token, $planId);
    answerTasks($this, $token, $session);

    expect(DB::table('learning_plan_days')->where('plan_id', $planId)->where('day_index', 1)->value('status'))
        ->toBe('ready');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/study/sessions/{$session['session_id']}/complete", [
            'ended_at' => now()->toIso8601String(),
        ])
        ->assertOk();

    // NO second session built — this is the whole point.
    expect(DB::table('learning_plan_days')->where('plan_id', $planId)->where('day_index', 1)->value('status'))
        ->toBe('done');
});

it('leaves the day alone when the sitting ended with a word still owed a card', function () {
    // A card never MET is what keeps the day open: stage A closes when every card of the day has
    // been introduced (one touch per stage, DAY-FIX-2), and a day with one intro unseen is not
    // passed, however many of its other cards were answered.
    [, $token, $planId] = startedPlan($this);

    $session = planSession($this, $token, $planId);
    // Everything except the LAST INTRO of the day.
    $lastIntro = null;
    foreach ($session['tasks'] as $i => $task) {
        if ($task['section'] === 'day' && $task['card']['exercise_mode'] === 'intro') {
            $lastIntro = $i;
        }
    }
    expect($lastIntro)->not->toBeNull();
    $partial = [
        'session_id' => $session['session_id'],
        'tasks' => array_values(array_filter($session['tasks'], static fn (array $t, int $i): bool => $i !== $lastIntro, ARRAY_FILTER_USE_BOTH)),
    ];
    answerTasks($this, $token, $partial);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/study/sessions/{$session['session_id']}/complete", [
            'ended_at' => now()->toIso8601String(),
        ])
        ->assertOk();

    expect(DB::table('learning_plan_days')->where('plan_id', $planId)->where('day_index', 1)->value('status'))
        ->toBe('ready');
});

// ── a LINE of another plan never travels ──────────────────────────────────────────────────────

/**
 * A term with a plan's `kind` on it, in this learner's pool and due — the shape a word left behind
 * by a finished plan has.
 */
function duePlanTerm(object $user, string $kind, string $text, string $translation): string
{
    $termId = duePoolTerm($user, 'en', 'ru', $text, $translation);
    DB::table('terms')->where('id', $termId)->update(['kind' => $kind, 'is_line' => $kind === 'line']);

    return $termId;
}

it('lets no word of another plan in, as a task or as an option — line or not', function () {
    // The live shape: «Hi, I'm Alex, and I work as a backend developer.» — the learner's own word,
    // their own pair, their own pool — dealt inside a HOLIDAY plan as something to study, out of an
    // interview plan they had abandoned. The `line` half of it was answered on 31.08 by excluding
    // lines; the word beside it travelled on, and «паспорт» is what that looked like on 01.09.
    [$user, $token, $planId] = startedPlan($this);

    $line = duePlanTerm($user, 'line', "Hi, I'm Alex, and I work as a backend developer.", 'Привет, я Алекс, и я работаю бэкенд-разработчиком.');
    $word = duePlanTerm($user, 'word', 'boarding pass', 'посадочный талон');

    $session = planSession($this, $token, $planId);

    $dealt = array_column(array_column($session['tasks'], 'card'), 'term_id');
    expect($dealt)->not->toContain($line)
        ->and($dealt)->not->toContain($word);

    // Nor as wrong answers. Both are `source = 'user'` words on a shelf of this learner's, so the
    // option reader would take them for one of THEIR ordinary cards — and a plan card prefers its
    // own plan's pool, which is what keeps them off this one.
    foreach ($session['tasks'] as $task) {
        expect($task['card']['options'] ?? [])
            ->not->toContain("Hi, I'm Alex, and I work as a backend developer.")
            ->not->toContain('boarding pass');
    }
});

it('offers a word no line as a wrong answer, whatever the session is carrying', function () {
    // «passport» offered «Hello. Do you have a reservation?» — not a wrong answer but a different
    // kind of question, and one that gives the right one away by length alone.
    [$user, $token, $planId] = startedPlan($this);
    // Option cards are stage B, the day after the words were met (DAY-FIX-2).
    walkDay($this, $token, $planId, 1);
    ageHistory($user->id, days: 1);

    $session = planSession($this, $token, $planId);

    $kinds = DB::table('terms')->pluck('kind', 'text')->all();
    $seen = 0;
    foreach ($session['tasks'] as $task) {
        $target = $kinds[$task['card']['answer']] ?? null;
        if ($target === 'line' || $task['card']['options'] === null) {
            continue;
        }
        foreach ($task['card']['options'] as $option) {
            expect($kinds[$option] ?? null)->not->toBe('line');
            $seen++;
        }
    }

    expect($seen)->toBeGreaterThan(0);   // guard: the session really did deal option cards
});

// ── where a review card says it came from ─────────────────────────────────────────────────────

it('names no shelf on a seam card, because every card of the sitting is this plan`s own', function () {
    // `origin` used to carry «Из плана: Отдых в Италии» / «Из коллекции: Аэропорт» over a top-up
    // card. There is no top-up, so there is no foreign shelf to name — and naming THIS plan over a
    // card of this plan's own earlier day is the sentence the owner read as a lie on 01.09. The
    // field stays on the wire (a client reading it must not break); it stays null.
    [$user, $token, $planId] = startedPlan($this, ['event_date' => now()->addDays(10)->format('Y-m-d')]);

    $termId = duePoolTerm($user, 'en', 'ru', 'boarding pass', 'посадочный талон');
    DB::table('collections')
        ->whereIn('id', DB::table('collection_items')->where('term_id', $termId)->pluck('collection_id'))
        ->update(['title' => 'Аэропорт']);

    walkDay($this, $token, $planId, 1);
    ageHistory($user->id, days: 8);

    $session = planSession($this, $token, $planId);

    expect(array_column($session['tasks'], 'section'))->toContain(PlanSessionTaskView::SECTION_REVIEW);

    foreach ($session['tasks'] as $task) {
        expect($task['origin'])->toBeNull()
            ->and($task['card']['term_id'])->not->toBe($termId);
    }
});
