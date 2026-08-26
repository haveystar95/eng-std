<?php

declare(strict_types=1);

use App\Modules\Shared\Domain\ValueObject\Ulid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

// learner() / seedCollectionWith() / addWordTo() / answerTimes() / profileFor() live in tests/Pest.php.

/**
 * `GET /home-plan` — THE HOME SCREEN'S DAY.
 *
 * The contract these tests pin is mostly about ABSENCE. Every block the design draws has a state in
 * which it is not drawn at all, and the screen's rule is «блок без данных не рисуется»: the client
 * can only obey it if the payload can say «нет данных» out loud. So a missing block is `null` or
 * `[]` here, and a `0` where a `null` belongs is a bug with a screen-shaped consequence — a
 * «0 слов» line the design says does not exist.
 */

/** @return array<string, mixed> the `data` envelope of GET /home-plan */
function homePlan(object $ctx, string $token): array
{
    return $ctx->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/home-plan')
        ->assertOk()
        ->json('data');
}

/**
 * Put one pair on the schedule N days out, off the ladder — the shape a word has once it has been
 * learned and is merely waiting. Written straight to the projection because these tests are about
 * what the READ MODEL says about a schedule, not about how SM-2 arrives at one.
 */
function scheduleAhead(string $userId, string $termId, int $days): void
{
    DB::table('user_term_progress')
        ->where('user_id', $userId)->where('term_id', $termId)
        ->update([
            'state' => 'review',
            'acquisition' => 'graduated',
            'interval_days' => $days,
            'due_at' => now()->addDays($days)->startOfDay(),
        ]);
}

/**
 * A store deck of the usual pair, owned by nobody — what «готовые наборы» are made of.
 *
 * Named for this file rather than `seedStoreCollection`: StoreApiTest already declares one, and a
 * top-level test function is global. Under `pest --parallel` a worker can load either file first,
 * so the collision is a fatal error rather than a shadowing.
 */
function homeStoreDeck(string $title, string $topic = 'Быт'): string
{
    $id = Ulid::generate();
    DB::table('collections')->insert([
        'id' => $id, 'owner_id' => null, 'type' => 'system', 'source' => 'curated',
        'title' => $title, 'topic' => $topic, 'source_lang' => 'ru', 'target_lang' => 'en',
        'visibility' => 'public', 'items_count' => 10,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return $id;
}

it('names the state and the whole composition of the day (17a)', function () {
    [$user, $token] = learner();

    // A repeat: walked off the ladder and left to fall due.
    [$col, $apple] = seedCollectionWith($user, 'apple', 'яблоко');
    answerTimes($this, $token, $apple, 'apple', times: 3, lastDaysAgo: 10);

    // A first meeting: in the pool, never shown.
    addWordTo($col, $user->id, 'bank', 'банк');
    // A swipe: in the catalogue, no verdict and no progress row.
    addWordTo($col, $user->id, 'chair', 'стул', enroll: false);

    $plan = homePlan($this, $token);

    expect($plan['state'])->toBe('plan')
        ->and($plan['session']['repeat'])->toBe(1)
        ->and($plan['session']['new'])->toBe(1)
        ->and($plan['session']['triage'])->toBe(1)
        // total is NOT repeat + new: the swipe pass is part of the day, not of the study session.
        ->and($plan['session']['total'])->toBe(3)
        ->and($plan['session']['triage_collection_id'])->toBe($col)
        ->and($plan['session']['triage_collection_title'])->toBe('apple');
});

it('estimates the session from cards times the learner own seconds per card', function () {
    [$user, $token] = learner();
    [$col] = seedCollectionWith($user, 'apple', 'яблоко', enroll: false);
    foreach (['bank', 'chair', 'door', 'egg'] as $word) {
        addWordTo($col, $user->id, $word, 'x', enroll: false);
    }

    $plan = homePlan($this, $token);
    $session = $plan['session'];

    // No answers yet ⇒ no personal pace ⇒ the documented default, and the estimate follows from it.
    expect($session['total'])->toBe(5)
        ->and($session['avg_seconds_per_card'])->toBe(8)
        ->and($session['estimated_minutes'])->toBe((int) round(5 * 8 / 60) ?: 1);
});

it('says nothing rather than zero when there is nothing to do (17c, the first day)', function () {
    [, $token] = learner();

    $plan = homePlan($this, $token);

    expect($plan['state'])->toBe('empty')
        // Every ABSENT block is absent, not zeroed.
        ->and($plan['today'])->toBeNull()
        ->and($plan['next_review'])->toBeNull()
        ->and($plan['continue'])->toBeNull()
        ->and($plan['session']['estimated_minutes'])->toBeNull()
        ->and($plan['session']['triage_collection_id'])->toBeNull()
        ->and($plan['in_work']['days_until_queue'])->toBeNull()
        ->and($plan['edge'])->toBe([])
        ->and($plan['hardest'])->toBe([])
        // …and the counters that ARE numbers say zero honestly.
        ->and($plan['session']['total'])->toBe(0)
        ->and($plan['in_work']['total'])->toBe(0);
});

it('reports the day as closed once the session is answered (17b)', function () {
    [$user, $token] = learner();
    [$col, $apple] = seedCollectionWith($user, 'apple', 'яблоко');
    $bank = addWordTo($col, $user->id, 'bank', 'банк');

    // `answerTimes` spreads its answers over the preceding days — the LAST of each three lands
    // today, which is what makes this the evening screen rather than a day nobody opened. The
    // schedule is then pinned by hand so the assertion is about the CONTRACT and not about which
    // interval SM-2 happened to fuzz its way to.
    answerTimes($this, $token, $apple, 'apple', times: 3);
    answerTimes($this, $token, $bank, 'bank', times: 3);
    scheduleAhead($user->id, $apple, 3);
    scheduleAhead($user->id, $bank, 3);

    $plan = homePlan($this, $token);

    expect($plan['state'])->toBe('done')
        ->and($plan['session']['total'])->toBe(0)
        ->and($plan['today']['answered'])->toBe(2)
        ->and($plan['next_review']['date'])->toBe(now()->addDays(3)->format('Y-m-d'))
        ->and($plan['next_review']['count'])->toBe(2);
});

it('counts only STUDY answers into the day — practice keeps the streak, not the plan', function () {
    [$user, $token] = learner();
    $apple = seedWordFor($user, 'apple', 'яблоко');
    scheduleAhead($user->id, $apple, 30);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/reviews/batch', ['reviews' => [[
            'id' => Ulid::generate(), 'term_id' => $apple, 'exercise_mode' => 'typing',
            'response' => 'apple', 'answered_at' => now()->toIso8601String(),
            'is_practice' => true, 'client_seq' => 1,
        ]]])->assertOk();

    // The answer counts as activity on /stats, and does not close the day here.
    expect(homePlan($this, $token)['today'])->toBeNull();
});

it('does not call a queue of untaught words a session (17d)', function () {
    [$user, $token] = learner();
    [$col] = seedCollectionWith($user, 'apple', 'яблоко');   // enrolled, never shown
    addWordTo($col, $user->id, 'bank', 'банк');

    $plan = homePlan($this, $token);

    // The words ARE offered — they are in the day's composition and the box says they are waiting —
    // but nothing is DUE, so the screen is «Всё повторено» over one button, not a session card.
    expect($plan['state'])->toBe('idle')
        ->and($plan['session']['new'])->toBe(2)
        ->and($plan['session']['repeat'])->toBe(0)
        ->and($plan['in_work']['waiting'])->toBe(2)
        ->and($plan['in_work']['new_remaining'])->toBeGreaterThan(0);
});

it('leaves a closed day closed, and calls the leftovers extra (17b)', function () {
    [$user, $token] = learner();
    [$col, $apple] = seedCollectionWith($user, 'apple', 'яблоко');
    addWordTo($col, $user->id, 'leash', 'поводок', enroll: false); // a swipe left over

    answerTimes($this, $token, $apple, 'apple', times: 3);
    scheduleAhead($user->id, $apple, 3);

    // The swipe is still on the shelf and still counted — but it is «сверх плана», not a reason to
    // reopen the day.
    $plan = homePlan($this, $token);

    expect($plan['session']['triage'])->toBe(1)
        ->and($plan['state'])->toBe('plan');

    // …until the swipe is done too, and then the day is closed with its answers still standing.
    DB::table('term_triages')->insert([
        'id' => Ulid::generate(), 'user_id' => $user->id,
        'term_id' => DB::table('collection_items')->where('collection_id', $col)
            ->where('term_id', '<>', $apple)->value('term_id'),
        'verdict' => 'known', 'decided_at' => now(), 'created_at' => now(), 'client_seq' => 1,
    ]);

    $plan = homePlan($this, $token);
    expect($plan['state'])->toBe('done')
        ->and($plan['today']['answered'])->toBe(1);
});

it('is idle, not empty, when the words exist and the schedule is simply ahead (17d)', function () {
    [$user, $token] = learner();
    $apple = seedWordFor($user, 'apple', 'яблоко');
    scheduleAhead($user->id, $apple, 30);

    $plan = homePlan($this, $token);

    expect($plan['state'])->toBe('idle')
        ->and($plan['session']['total'])->toBe(0)
        ->and($plan['today'])->toBeNull()
        ->and($plan['in_work']['total'])->toBe(1)
        ->and($plan['next_review']['date'])->toBe(now()->addDays(30)->format('Y-m-d'))
        ->and($plan['next_review']['count'])->toBe(1);
});

it('sizes the box: pool, queue, pace and when the queue moves', function () {
    [$user, $token] = learner();
    profileFor($user, ['daily_goal' => 20, 'timezone' => 'UTC']);
    [$col] = seedCollectionWith($user, 'w0', 'x');
    for ($i = 1; $i < 25; $i++) {
        addWordTo($col, $user->id, "w{$i}", 'x');
    }

    $plan = homePlan($this, $token)['in_work'];

    expect($plan['total'])->toBe(25)
        ->and($plan['waiting'])->toBe(25)      // enrolled, never shown
        ->and($plan['per_day'])->toBe(20)
        ->and($plan['new_remaining'])->toBe(20)
        // 20 go today, the last 5 tomorrow.
        ->and($plan['days_until_queue'])->toBe(2);
});

it('has no answer in days when the learner takes no new words at all', function () {
    [$user, $token] = learner();
    profileFor($user, ['daily_goal' => 0, 'timezone' => 'UTC']);
    seedWordFor($user, 'apple', 'яблоко');

    $plan = homePlan($this, $token)['in_work'];

    expect($plan['waiting'])->toBe(1)
        ->and($plan['per_day'])->toBe(0)
        // «Никогда» is not a number of days, and 0 would read as «сегодня».
        ->and($plan['days_until_queue'])->toBeNull();
});

it('lists the words about to fall out, dated — and only those near the edge', function () {
    [$user, $token] = learner();
    [$col, $apple] = seedCollectionWith($user, 'apple', 'яблоко');
    $bank = addWordTo($col, $user->id, 'bank', 'банк');
    $far = addWordTo($col, $user->id, 'chair', 'стул');

    scheduleAhead($user->id, $apple, 1);
    scheduleAhead($user->id, $bank, 2);
    scheduleAhead($user->id, $far, 30);   // scheduled, not slipping

    $edge = homePlan($this, $token)['edge'];

    expect($edge)->toHaveCount(2)
        ->and($edge[0]['term_id'])->toBe($apple)
        ->and($edge[0]['text'])->toBe('apple')
        ->and($edge[0]['translation'])->toBe('яблоко')
        ->and($edge[0]['in_days'])->toBe(1)
        ->and($edge[0]['due_on'])->toBe(now()->addDay()->format('Y-m-d'))
        ->and($edge[1]['in_days'])->toBe(2);
});

it('names what the last run got wrong, worst first', function () {
    [$user, $token] = learner();
    [$col, $apple] = seedCollectionWith($user, 'apple', 'яблоко');
    addWordTo($col, $user->id, 'bank', 'банк');

    $session = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/study/sessions', ['collection_id' => $col])
        ->assertOk()->json('data');

    $seq = 0;
    $reviews = [];
    foreach ($session['cards'] as $card) {
        if ($card['term_id'] !== $apple || $card['exercise_mode'] === 'intro') {
            continue;
        }
        $reviews[] = [
            'id' => Ulid::generate(), 'term_id' => $apple, 'session_id' => $session['session_id'],
            'exercise_mode' => $card['exercise_mode'], 'response' => 'заведомо неверно',
            'answered_at' => now()->toIso8601String(), 'client_seq' => ++$seq,
        ];
    }

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/reviews/batch', ['reviews' => $reviews])->assertOk();

    $hardest = homePlan($this, $token)['hardest'];

    expect($hardest)->not->toBe([])
        ->and($hardest[0]['term_id'])->toBe($apple)
        ->and($hardest[0]['text'])->toBe('apple')
        ->and($hardest[0]['errors'])->toBeGreaterThanOrEqual(1);
});

it('offers to continue the collection that was started and left', function () {
    [$user, $token] = learner();
    [$col] = seedCollectionWith($user, 'Ветклиника', 'x');       // 1 term, enrolled ⇒ started
    addWordTo($col, $user->id, 'leash', 'поводок', enroll: false); // …and 2 left to swipe
    addWordTo($col, $user->id, 'vet', 'ветеринар', enroll: false);

    DB::table('term_triages')->insert([
        'id' => Ulid::generate(), 'user_id' => $user->id,
        'term_id' => DB::table('collection_items')->where('collection_id', $col)->value('term_id'),
        'verdict' => 'unknown', 'decided_at' => now()->subDays(5), 'created_at' => now()->subDays(5),
        'client_seq' => 1,
    ]);

    $continue = homePlan($this, $token)['continue'];

    expect($continue['collection_id'])->toBe($col)
        ->and($continue['title'])->toBe('Ветклиника')
        ->and($continue['done'])->toBe(1)
        ->and($continue['total'])->toBe(3)
        ->and($continue['remaining'])->toBe(2)
        ->and($continue['abandoned_days'])->toBe(5);
});

it('does not offer to continue a collection nobody has opened yet', function () {
    [$user, $token] = learner();
    [$col] = seedCollectionWith($user, 'Аэропорт', 'x', enroll: false);
    addWordTo($col, $user->id, 'gate', 'выход', enroll: false);

    // Untouched is not abandoned: there is a swipe pass to offer, but nothing to «continue».
    $plan = homePlan($this, $token);

    expect($plan['continue'])->toBeNull()
        ->and($plan['session']['triage'])->toBe(2);
});

it('counts the ready-made sets the learner does not have yet, with a taste of them', function () {
    [$user, $token] = learner();
    homeStoreDeck('У врача');
    homeStoreDeck('Аэропорт');
    $mine = homeStoreDeck('Аренда');

    DB::table('user_collections')->insert([
        'user_id' => $user->id, 'collection_id' => $mine, 'added_at' => now(),
    ]);

    $store = homePlan($this, $token)['store'];

    // «Аренда» is already on the shelf, so it is neither counted nor offered.
    expect($store['count'])->toBe(2)
        ->and($store['topics'])->toHaveCount(2)
        ->and($store['topics'])->not->toContain('Аренда');
});

it('refuses an anonymous caller', function () {
    $this->getJson('/api/v1/home-plan')->assertUnauthorized();
});
