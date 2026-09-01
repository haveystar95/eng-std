<?php

declare(strict_types=1);

use App\Modules\Learning\Application\Command\EndPlan;
use App\Modules\Learning\Application\Command\EndPlanHandler;
use App\Modules\Learning\Domain\ValueObject\PlanEnding;
use App\Modules\Learning\Domain\ValueObject\PlanId;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * A PLAN'S LESSON IS MADE OF THAT PLAN'S OWN CARDS, AND OF NOTHING ELSE.
 *
 * The owner's screen on 01.09: plan «Аренда жилья», day 1, task 47 of 67, section «Повторение · из
 * плана: Отдых в Италии», the card «паспорт» to be recognised among `utilities` / `available` /
 * `deposit`. A card of one plan inside the sitting of another, with three wrong answers off a third
 * shelf. Every one of them arrived legally under the rule of 31.08 — bucket 3 topped a plan's day up
 * with everything else the planner had due (DECISIONS п. 204). That rule is cancelled here.
 *
 * ## The fixture is the state the incident actually happened in
 *
 * The наряд asked for two plans BOTH holding their words. That state does not exist and cannot be
 * built: `StartPlanHandler` refuses a new plan while another is `active` OR `paused`, and a partial
 * unique index (`learning_plans_one_active_uidx`) holds the `active` half in the database. At most
 * one plan holds terms at a time — and the plan that leaked into the owner's lesson, «Отдых в
 * Италии», was `abandoned`: an ENDED plan releases its words into the ordinary pool
 * ({@see \App\Modules\Learning\Application\Port\PlanTermReleaser}), and that pool is what bucket 3
 * poured back in.
 *
 * So plan A is ended and plan B is running, which is the shape that produced the screenshot and the
 * hardest one for the filter — A's words are loose, overdue, and in the right language pair, i.e.
 * everything bucket 3 was looking for. Everything else is the наряд's fixture verbatim: one learner,
 * one pair, a catalogue shelf being studied, three hand-saved words, and nothing anywhere that is
 * not overdue.
 */
beforeEach(function (): void {
    fakePlanModel();
    // Both plan trainers ship dark; the release ritual is not what this file is about.
    DB::table('learning_mode_settings')->where('scope', 'global')->whereNull('user_id')->update(['enabled' => true]);
});

/**
 * One learner, two plans, a catalogue shelf in work and three hand-saved words.
 *
 * @return array{user: \App\Modules\Identity\Infrastructure\Eloquent\User, token: string, planA: string, planB: string, seq: int, shelfTerms: list<string>, manualTerms: list<string>}
 */
function scopeFixture(object $ctx): array
{
    [$user, $token] = learner();
    profileFor($user, ['native_language' => 'ru', 'target_language' => 'en']);

    // PLAN A — walked through day 1, so its day-1 words carry real history and its day 2 is written,
    // then abandoned, which puts those answered words back into the ordinary queue. `[tag:2]` makes
    // its cards its own: terms are deduplicated globally, and without the mark both plans would
    // stand on the same rows and this whole file would be vacuous.
    $planA = startedPlanFor($ctx, $token, [
        'goal_text' => 'Отдых в Италии, надо объясниться на отдыхе [tag:2]',
        'event_date' => now()->addDays(10)->format('Y-m-d'),
    ]);
    $seq = walkDay($ctx, $token, $planA, 1);
    $ctx->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/plans/{$planA}/abandon")->assertOk();

    // PLAN B — the one the learner is studying now.
    $planB = startedPlanFor($ctx, $token, [
        'goal_text' => 'Аренда жилья за границей, надо снять квартиру [tag:3]',
        'event_date' => now()->addDays(10)->format('Y-m-d'),
    ]);

    // A CATALOGUE SHELF THE LEARNER IS WORKING ON. Subscribed and enrolled: these are their own
    // words now, whatever shelf they came off.
    $shelfId = Ulid::generate();
    DB::table('collections')->insert([
        'id' => $shelfId, 'owner_id' => null, 'type' => 'system', 'source' => 'curated',
        'title' => 'Витрина: аэропорт', 'topic' => null, 'source_lang' => 'ru', 'target_lang' => 'en',
        'visibility' => 'public', 'items_count' => 3, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('user_collections')->insert([
        'user_id' => $user->id, 'collection_id' => $shelfId, 'added_at' => now(), 'is_pinned' => false,
    ]);
    $shelfTerms = [];
    foreach ([['utilities', 'коммунальные'], ['available', 'доступный'], ['deposit', 'залог']] as $i => [$text, $translation]) {
        $shelfTerms[] = scopeShelfTerm($shelfId, $text, $translation, $i);
    }
    foreach ($shelfTerms as $termId) {
        enrollTerm($user, $termId);
    }

    // THREE WORDS SAVED BY HAND, on a folder of the learner's own.
    [$folderId, $first] = seedCollectionWith($user, 'passport', 'паспорт');
    $manualTerms = [
        $first,
        addWordTo($folderId, $user->id, 'luggage', 'багаж'),
        addWordTo($folderId, $user->id, 'boarding', 'посадка'),
    ];

    // EVERYTHING OVERDUE. The plans' words through their own history (`ageHistory` moves the review
    // log and the schedule together, which is the honest inversion of «прошла неделя»); the rest by
    // standing where an enrolled pair stands before its first answer — unfinished, and therefore
    // ahead of everything, with no `due_at` to be late.
    ageHistory($user->id, days: 8);
    DB::table('user_term_progress')
        ->where('user_id', $user->id)
        ->whereIn('term_id', [...$shelfTerms, ...$manualTerms])
        ->update(['acquisition' => 'learning', 'due_at' => null]);

    return [
        'user' => $user, 'token' => $token, 'planA' => $planA, 'planB' => $planB,
        // WHERE THE ANSWER COUNTER GOT TO. `client_seq` is per learner and monotonic — it is the
        // order progress is folded in — so a second walk that restarted at 1 would be answering
        // «before» the first one and fold into nothing.
        'seq' => $seq,
        'shelfTerms' => $shelfTerms, 'manualTerms' => $manualTerms,
    ];
}

/** One catalogue term on a shelf — the raw rows, because no command creates a curated item. */
function scopeShelfTerm(string $collectionId, string $text, string $translation, int $position): string
{
    $termId = Ulid::generate();
    DB::table('terms')->insert([
        'id' => $termId, 'lang' => 'en', 'text' => $text, 'normalized_text' => mb_strtolower($text),
        'type' => 'word', 'source' => 'ai', 'cefr' => 'A2', 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('term_translations')->insert([
        'id' => Ulid::generate(), 'term_id' => $termId, 'lang' => 'ru', 'text' => $translation,
        'is_primary' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('collection_items')->insert([
        'id' => Ulid::generate(), 'collection_id' => $collectionId, 'term_id' => $termId,
        'position' => $position, 'created_at' => now(), 'updated_at' => now(),
    ]);

    return $termId;
}

/** Every term of every written day of a plan. @return list<string> */
function planTermIds(string $planId): array
{
    return array_values(array_map('strval', DB::table('collection_items')
        ->whereIn('collection_id', DB::table('learning_plan_days')->where('plan_id', $planId)->pluck('collection_id'))
        ->pluck('term_id')
        ->all()));
}

/** The terms of ONE day of a plan. @return list<string> */
function planDayTermIds(string $planId, int $dayIndex): array
{
    return array_values(array_map('strval', DB::table('collection_items')
        ->where('collection_id', DB::table('learning_plan_days')
            ->where('plan_id', $planId)->where('day_index', $dayIndex)->value('collection_id'))
        ->pluck('term_id')
        ->all()));
}

/**
 * Everything these terms could show up as on a card: their own text and every translation of it.
 *
 * Both halves, because the options of a card are not always term texts — the rung-1 recognition card
 * is identity-graded and its options ARE translations.
 *
 * @param  list<string>  $termIds
 * @return list<string>
 */
function surfaceTexts(array $termIds): array
{
    return array_values(array_unique([
        ...array_map('strval', DB::table('terms')->whereIn('id', $termIds)->pluck('text')->all()),
        ...array_map('strval', DB::table('term_translations')->whereIn('term_id', $termIds)->pluck('text')->all()),
    ]));
}

/** Every option string dealt anywhere in a session. @return list<string> */
function sessionOptions(array $session): array
{
    $out = [];
    foreach ($session['tasks'] as $task) {
        foreach ($task['card']['options'] ?? [] as $option) {
            $out[] = (string) $option;
        }
    }

    return $out;
}

/** The session's cards, by section. @return list<array<string, mixed>> */
function tasksInSection(array $session, string $section): array
{
    return array_values(array_filter(
        $session['tasks'],
        static fn (array $t): bool => $t['section'] === $section,
    ));
}

// ── the plan's own lesson ─────────────────────────────────────────────────────────────────────

it('deals day 1 of a plan out of that plan alone — no seam, and no wrong answer off another shelf', function () {
    $f = scopeFixture($this);

    $session = planSession($this, $f['token'], $f['planB'], 1);

    $ownTerms = planTermIds($f['planB']);
    $foreign = surfaceTexts([...planTermIds($f['planA']), ...$f['shelfTerms'], ...$f['manualTerms']]);

    expect($session['tasks'])->not->toBeEmpty();

    foreach ($session['tasks'] as $task) {
        expect($task['card']['term_id'])->toBeIn($ownTerms)
            // Day 1 of any plan has no earlier day to revise, so the seam does not exist.
            ->and($task['section'])->toBe('day');
    }

    // «паспорт» among `utilities` / `available` / `deposit` — the card off the screenshot, and every
    // one of its four texts belongs to somebody other than this plan.
    foreach (sessionOptions($session) as $option) {
        expect($option)->not->toBeIn($foreign);
    }

    // …and the count in the header describes that and only that.
    expect($session['day_task_count'])->toBe(count($session['tasks']));
});

it('fills the seam of day 2 with the plan`s OWN first day and nothing else', function () {
    $f = scopeFixture($this);

    walkDay($this, $f['token'], $f['planB'], 1, $f['seq']);
    ageHistory($f['user']->id, days: 8);

    $session = planSession($this, $f['token'], $f['planB']);

    $day1 = planDayTermIds($f['planB'], 1);
    $review = tasksInSection($session, 'review');

    expect($session['day_index'])->toBe(2)
        ->and($review)->not->toBeEmpty();

    foreach ($review as $task) {
        expect($task['card']['term_id'])->toBeIn($day1);
    }

    // The day's own count is the day's own cards — the seam is not part of it.
    expect($session['day_task_count'])->toBe(count($session['tasks']) - count($review));
});

it('never lets the other plan`s words into a plan session, in either direction', function () {
    $f = scopeFixture($this);

    walkDay($this, $f['token'], $f['planB'], 1, $f['seq']);
    ageHistory($f['user']->id, days: 8);

    $aTerms = planTermIds($f['planA']);
    $bTerms = planTermIds($f['planB']);

    // B's sitting holds nothing of A — not as a card, and not as a wrong answer.
    $b = planSession($this, $f['token'], $f['planB']);
    $aSurface = surfaceTexts($aTerms);
    foreach ($b['tasks'] as $task) {
        expect($task['card']['term_id'])->toBeIn($bTerms);
    }
    foreach (sessionOptions($b) as $option) {
        expect($option)->not->toBeIn($aSurface);
    }

    // And A's own sitting, opened after it ended, holds nothing of B.
    $a = planSession($this, $f['token'], $f['planA']);
    $bSurface = surfaceTexts($bTerms);
    expect($a['tasks'])->not->toBeEmpty();
    foreach ($a['tasks'] as $task) {
        expect($task['card']['term_id'])->toBeIn($aTerms);
    }
    foreach (sessionOptions($a) as $option) {
        expect($option)->not->toBeIn($bSurface);
    }
});

// ── and the other direction ───────────────────────────────────────────────────────────────────

it('keeps every word of a running plan out of the ordinary session, answered ones included', function () {
    $f = scopeFixture($this);

    // ANSWERED ones included is the whole point: the plan's hold is stamped on the progress row, and
    // a row rewritten by the scheduler is where a hold gets quietly dropped.
    walkDay($this, $f['token'], $f['planB'], 1, $f['seq']);
    ageHistory($f['user']->id, days: 8);

    $held = planTermIds($f['planB']);

    $session = $this->withHeader('Authorization', "Bearer {$f['token']}")
        ->postJson('/api/v1/study/sessions', ['session_id' => (string) Ulid::generate(), 'limit' => 60])
        ->assertOk()
        ->json('data');

    $dealt = array_values(array_unique(array_map(
        static fn (array $card): string => (string) $card['term_id'],
        $session['cards'],
    )));

    foreach ($dealt as $termId) {
        expect($termId)->not->toBeIn($held);
    }

    // The learner's own words are still there — a plan suspends nothing but itself.
    foreach ([...$f['shelfTerms'], ...$f['manualTerms']] as $termId) {
        expect($termId)->toBeIn($dealt);
    }
});

it('counts «Повторить N» over the learner`s own words only', function () {
    $f = scopeFixture($this);

    // With day 1 of the running plan ANSWERED — the state in which a dropped hold shows up as the
    // learner being asked for the same words twice, under two headings.
    walkDay($this, $f['token'], $f['planB'], 1, $f['seq']);
    ageHistory($f['user']->id, days: 8);

    $home = $this->withHeader('Authorization', "Bearer {$f['token']}")
        ->getJson('/api/v1/home-plan')
        ->assertOk()
        ->json('data');

    // The shelf and the three hand-saved words. Not one card of the plan the learner is running —
    // and not one of the plan they abandoned either, because an ended plan gives nothing back.
    expect($home['session']['repeat'])->toBe(count($f['shelfTerms']) + count($f['manualTerms']));
});

it('does NOT give the words back when the plan ends — an ended plan is an archive (Э8)', function () {
    $f = scopeFixture($this);

    walkDay($this, $f['token'], $f['planB'], 1, $f['seq']);
    ageHistory($f['user']->id, days: 8);

    // The words of plan B the learner really ANSWERED. Under the old rule these were exactly the
    // ones an ending kept in the pool — «18 слов ушли в общее повторение». The owner reversed that
    // on 01.09: days spent on a word inside a course is not by itself a reason for it to start
    // arriving in a daily queue nobody chose.
    $answered = array_values(array_unique(array_map('strval', DB::table('reviews')
        ->where('user_id', $f['user']->id)
        ->whereIn('term_id', planTermIds($f['planB']))
        ->pluck('term_id')
        ->all())));
    expect($answered)->not->toBeEmpty();

    (app(EndPlanHandler::class))(new EndPlan(
        PlanId::fromString($f['planB']),
        UserId::fromString($f['user']->id),
        PlanEnding::Complete,
    ));

    $dealt = array_map(
        static fn (array $c): string => (string) $c['term_id'],
        $this->withHeader('Authorization', "Bearer {$f['token']}")
            ->postJson('/api/v1/study/sessions', ['session_id' => (string) Ulid::generate(), 'limit' => 60])
            ->assertOk()
            ->json('data.cards'),
    );

    expect(array_values(array_intersect($answered, $dealt)))->toBe([])
        // Out of the queue, and out of the counter that promises one.
        ->and($this->withHeader('Authorization', "Bearer {$f['token']}")
            ->getJson('/api/v1/home-plan')->assertOk()->json('data.session.repeat'))
        ->toBe(count($f['shelfTerms']) + count($f['manualTerms']));

    // The archive itself is intact: the plan, its days and every answer are still there to read.
    expect(DB::table('learning_plan_days')->where('plan_id', $f['planB'])->count())->toBeGreaterThan(0)
        ->and(DB::table('reviews')->where('user_id', $f['user']->id)->whereIn('term_id', $answered)->count())
        ->toBeGreaterThan(0);
});

it('keeps an ABANDONED plan`s words out of the ordinary session and out of «Повторить»', function () {
    $f = scopeFixture($this);

    // Plan A was walked and then abandoned inside the fixture — the owner's «Отдых в Италии», whose
    // words used to come back due for ever and turned up inside another plan's lesson.
    $abandoned = planTermIds($f['planA']);

    $dealt = array_map(
        static fn (array $c): string => (string) $c['term_id'],
        $this->withHeader('Authorization', "Bearer {$f['token']}")
            ->postJson('/api/v1/study/sessions', ['session_id' => (string) Ulid::generate(), 'limit' => 60])
            ->assertOk()
            ->json('data.cards'),
    );

    expect(array_values(array_intersect($abandoned, $dealt)))->toBe([])
        ->and($this->withHeader('Authorization', "Bearer {$f['token']}")
            ->getJson('/api/v1/home-plan')->assertOk()->json('data.session.repeat'))
        ->toBe(count($f['shelfTerms']) + count($f['manualTerms']));
});

it('refuses a second plan while one is running — why two holding plans cannot be built', function () {
    [$user, $token] = learner();
    profileFor($user, ['native_language' => 'ru', 'target_language' => 'en']);

    startedPlanFor($this, $token, ['goal_text' => 'Первый план, идём в банк [tag:2]']);

    $second = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/plans', [
            'goal_text' => 'Второй план, идём к врачу [tag:3]',
            'target_lang' => 'en', 'level' => 'basic',
            'event_date' => now()->addDays(2)->format('Y-m-d'), 'minutes_per_day' => 20,
        ])->assertCreated()->json('data');
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/plans/{$second['id']}/outline")->assertOk();

    // Active, and a PAUSE does not open the door either: pausing keeps the hold, so a paused plan
    // still blocks. Two plans holding words at once is not a state this system has.
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/plans/{$second['id']}/start")->assertStatus(409);
});
