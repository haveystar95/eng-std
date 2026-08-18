<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Eloquent\Profile;
use App\Modules\Identity\Infrastructure\Eloquent\User;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * The live-observation screen's API.
 *
 * Every fixture below is built through the DEVICE's own endpoints — intros and answers go in via
 * `/api/v1/reviews/batch`, the «знаю» через `/api/v1/triage/batch` — and never by writing
 * `user_term_progress` directly. That is the point of these tests: the panel has to report the rung
 * the system actually stored, so a fixture that hand-set the columns would agree with the reader
 * while both disagreed with the app.
 */

/** Show the intro card for a term (rung 0 — no answer, no review row). */
function ladderIntro(string $token, string $termId, string $shownAt): void
{
    test()->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/reviews/batch', [
            'exposures' => [['term_id' => $termId, 'shown_at' => $shownAt]],
        ])
        ->assertOk();
}

/** Answer one card at a given rung. `$response` decides whether the server grades it correct. */
function ladderAnswer(
    string $token,
    string $termId,
    int $step,
    string $response,
    int $seq,
    string $answeredAt,
    string $mode = 'multiple_choice',
): void {
    test()->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/reviews/batch', [
            'reviews' => [[
                'id' => Ulid::generate(),
                'term_id' => $termId,
                'exercise_mode' => $mode,
                'ladder_step' => $step,
                'response' => $response,
                'answered_at' => $answeredAt,
                'client_seq' => $seq,
            ]],
        ])
        ->assertOk();
}

/**
 * A learner with one word on each rung that matters, plus the collections they came from.
 *
 * @return array{0: User, 1: string, 2: array<string, string>, 3: array<string, string>}
 *         [user, adminToken, termIds keyed by word, collectionIds keyed by title]
 */
function ladderFixture(): array
{
    $user = User::factory()->create();
    Profile::create(['user_id' => $user->id, 'daily_goal' => 10]);
    $token = $user->createToken('phone')->plainTextToken;

    [$fruit, $apple] = adminSeedTerm($user, 'Fruit', 'apple', 'яблоко');
    [, $bank] = adminSeedTerm($user, 'Money', 'bank', 'банк');
    [$pets, $cat] = adminSeedTerm($user, 'Pets', 'cat', 'кот');

    // apple — introduced, then one correct forward-recognition answer: the rung moves to 2. Rung 1
    // is a TAP and is graded by identity, so the "response" is the tapped option's term id, not the
    // word — sending the text here would grade as a miss and the pair would never leave rung 1.
    ladderIntro($token, $apple, now()->subMinutes(9)->toIso8601String());
    ladderAnswer($token, $apple, 1, $apple, 10, now()->subMinutes(8)->toIso8601String());

    // bank — introduced and nothing more: it stands at the first recognition rung.
    ladderIntro($token, $bank, now()->subMinutes(7)->toIso8601String());

    // cat — self-assessed «знаю» in triage: outside the ladder entirely.
    test()->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/triage/batch', [
            'triages' => [[
                'id' => Ulid::generate(),
                'term_id' => $cat,
                'verdict' => 'known',
                'decided_at' => now()->subMinutes(6)->toIso8601String(),
                'client_seq' => 20,
            ]],
        ])
        ->assertOk();

    [, $adminToken] = adminActor();

    return [
        $user,
        $adminToken,
        ['apple' => $apple, 'bank' => $bank, 'cat' => $cat],
        ['fruit' => $fruit, 'pets' => $pets],
    ];
}

it('reports the rung each pair stands on, and a dash-worthy null for a known one', function () {
    [$user, $adminToken, $terms] = ladderFixture();

    $body = $this->withHeader('Authorization', "Bearer {$adminToken}")
        ->getJson("/admin/api/users/{$user->id}/ladder")
        ->assertOk()
        ->json();

    $byTerm = collect($body['data'])->keyBy('term_id');
    expect($byTerm)->toHaveCount(3);

    // The word that passed one recognition step stands on rung 2 — the rung the ladder derives,
    // not the review count (its single answer would have said 1).
    $apple = $byTerm[$terms['apple']];
    expect($apple['ladder_step'])->toBe(2)
        ->and($apple['acquisition'])->toBe('learning')
        ->and($apple['learning_step'])->toBe(2)
        ->and($apple['text'])->toBe('apple')
        ->and($apple['translation'])->toBe('яблоко')
        ->and($apple['exposed_at'])->not->toBeNull()
        ->and($apple['last_review']['ladder_step'])->toBe(1)     // the card WAS dealt at rung 1
        ->and($apple['last_review']['is_correct'])->toBeTrue()
        ->and($apple['last_review']['response'])->toBe($terms['apple'])
        ->and($apple['last_review']['exercise_mode'])->toBe('multiple_choice')
        ->and($apple['collections'])->toHaveCount(1)
        ->and($apple['collections'][0]['title'])->toBe('Fruit');

    // Introduced and no further: rung 1, and no review at all.
    $bank = $byTerm[$terms['bank']];
    expect($bank['ladder_step'])->toBe(1)
        ->and($bank['learning_step'])->toBe(1)
        ->and($bank['last_review'])->toBeNull()
        ->and($bank['exposed_at'])->not->toBeNull();

    // «Знаю» is OUTSIDE the ladder: null, which the screen draws as a dash. Never rung 0.
    $cat = $byTerm[$terms['cat']];
    expect($cat['ladder_step'])->toBeNull()
        ->and($cat['state'])->toBe('known')
        ->and($cat['exposed_at'])->toBeNull();

    // The counters partition the pairs — `known` is counted once, not also as its acquisition.
    expect($body['counts'])->toMatchArray([
        'total' => 3,
        'new' => 0,
        'learning' => 2,
        'graduated' => 0,
        'known' => 1,
    ]);
});

it('filters the pairs by collection, phase and due-only', function () {
    [$user, $adminToken, $terms, $collections] = ladderFixture();
    $url = "/admin/api/users/{$user->id}/ladder";

    $byCollection = $this->withHeader('Authorization', "Bearer {$adminToken}")
        ->getJson($url . '?collection_id=' . $collections['pets'])
        ->assertOk()
        ->json();
    expect(collect($byCollection['data'])->pluck('term_id')->all())->toBe([$terms['cat']]);
    // The counters follow the collection filter, so the header describes the slice on screen.
    expect($byCollection['counts']['total'])->toBe(1);

    $known = $this->withHeader('Authorization', "Bearer {$adminToken}")
        ->getJson($url . '?phase=known')
        ->assertOk()
        ->json();
    expect(collect($known['data'])->pluck('term_id')->all())->toBe([$terms['cat']]);
    // Phase narrows the ROWS but not the counters — the split above the table stays whole.
    expect($known['counts']['total'])->toBe(3);

    $learning = $this->withHeader('Authorization', "Bearer {$adminToken}")
        ->getJson($url . '?phase=learning')
        ->assertOk()
        ->json();
    expect(collect($learning['data'])->pluck('term_id')->all())
        ->toContain($terms['apple'])->toContain($terms['bank'])->not->toContain($terms['cat']);

    // Nothing on the recognition rungs is scheduled, so «только due» empties the table: a ladder
    // pair has no due date at all. The known pair's verification check is dated in the future.
    $due = $this->withHeader('Authorization', "Bearer {$adminToken}")
        ->getJson($url . '?due=1')
        ->assertOk()
        ->json();
    expect($due['data'])->toBe([]);

    $this->withHeader('Authorization', "Bearer {$adminToken}")
        ->getJson($url . '?phase=nonsense')
        ->assertStatus(422);
});

it('pages the pairs', function () {
    [$user, $adminToken] = ladderFixture();
    $url = "/admin/api/users/{$user->id}/ladder";

    $first = $this->withHeader('Authorization', "Bearer {$adminToken}")
        ->getJson($url . '?per_page=2')->assertOk()->json();
    $second = $this->withHeader('Authorization', "Bearer {$adminToken}")
        ->getJson($url . '?per_page=2&page=2')->assertOk()->json();

    expect($first['data'])->toHaveCount(2)
        ->and($second['data'])->toHaveCount(1)
        ->and($first['meta']['total'])->toBe(3)
        ->and($first['meta']['per_page'])->toBe(2);

    // No row appears on both pages — the ordering is total, not just "mostly".
    $ids = array_merge(
        collect($first['data'])->pluck('term_id')->all(),
        collect($second['data'])->pluck('term_id')->all(),
    );
    expect(array_unique($ids))->toHaveCount(3);
});

it('lists learners for the selector, most recently active first', function () {
    [$user, $adminToken] = ladderFixture();

    // A second learner who has done nothing at all — they exist, but they sort last.
    $idle = User::factory()->create();
    Profile::create(['user_id' => $idle->id, 'daily_goal' => 10]);

    $rows = $this->withHeader('Authorization', "Bearer {$adminToken}")
        ->getJson('/admin/api/ladder/learners')
        ->assertOk()
        ->json('data');

    expect($rows[0]['id'])->toBe($user->id)
        ->and($rows[0]['pairs_count'])->toBe(3)
        ->and($rows[0]['last_activity_at'])->not->toBeNull()
        ->and($rows[0]['email'])->toBe($user->email);

    $idleRow = collect($rows)->firstWhere('id', $idle->id);
    expect($idleRow['last_activity_at'])->toBeNull()
        ->and($idleRow['pairs_count'])->toBe(0)
        ->and(array_search($idle->id, array_column($rows, 'id'), true))
        ->toBeGreaterThan(array_search($user->id, array_column($rows, 'id'), true));
});

it('streams answers, intros and triage verdicts as one feed, newest first', function () {
    [$user, $adminToken, $terms] = ladderFixture();

    $events = $this->withHeader('Authorization', "Bearer {$adminToken}")
        ->getJson("/admin/api/users/{$user->id}/ladder/events")
        ->assertOk()
        ->json('data');

    // Two exposures, one answer, and the «cat» triage — otherwise a known word would show up on the
    // ladder screen with no event explaining how it got there.
    expect($events)->toHaveCount(4);

    $kinds = array_column($events, 'kind');
    expect($kinds)->toContain('review')->toContain('exposure')->toContain('triage');

    // Newest first: `cat`'s triage (6 minutes ago) is the last thing that happened, ahead of
    // `bank`'s intro (7 minutes ago).
    expect($events[0]['term_id'])->toBe($terms['cat'])
        ->and($events[0]['kind'])->toBe('triage')
        ->and($events[0]['term_text'])->toBe('cat')
        ->and($events[0]['verdict'])->toBe('known')
        ->and($events[0]['client_seq'])->toBe(20)
        ->and($events[0]['grade'])->toBeNull()
        ->and($events[0]['ladder_step'])->toBeNull()
        ->and($events[0]['is_correct'])->toBeNull();

    expect($events[1]['term_id'])->toBe($terms['bank'])
        ->and($events[1]['kind'])->toBe('exposure')
        ->and($events[1]['term_text'])->toBe('bank')
        ->and($events[1]['grade'])->toBeNull()
        ->and($events[1]['ladder_step'])->toBeNull();

    $answer = collect($events)->firstWhere('kind', 'review');
    expect($answer['term_id'])->toBe($terms['apple'])
        ->and($answer['is_correct'])->toBeTrue()
        ->and($answer['ladder_step'])->toBe(1)
        ->and($answer['client_seq'])->toBe(10)
        ->and($answer['exercise_mode'])->toBe('multiple_choice');

    // `limit` truncates from the newest end.
    $one = $this->withHeader('Authorization', "Bearer {$adminToken}")
        ->getJson("/admin/api/users/{$user->id}/ladder/events?limit=1")
        ->assertOk()
        ->json('data');
    expect($one)->toHaveCount(1)->and($one[0]['term_id'])->toBe($terms['cat']);
});

it('reads nothing and writes nothing without an admin token', function () {
    [$user, $adminToken] = ladderFixture();

    $this->getJson("/admin/api/users/{$user->id}/ladder")->assertUnauthorized();
    $this->getJson('/admin/api/ladder/learners')->assertUnauthorized();
    $this->getJson("/admin/api/users/{$user->id}/ladder/events")->assertUnauthorized();

    // A user's own Sanctum token is not an admin token — the guard's provider is the admins table.
    $userToken = $user->createToken('phone-2')->plainTextToken;
    $this->withHeader('Authorization', "Bearer {$userToken}")
        ->getJson("/admin/api/users/{$user->id}/ladder")
        ->assertUnauthorized();

    // And the endpoints themselves change nothing: the progress rows are byte-identical after.
    $before = DB::table('user_term_progress')->orderBy('term_id')->get()->toJson();
    $this->withHeader('Authorization', "Bearer {$adminToken}")
        ->getJson("/admin/api/users/{$user->id}/ladder")->assertOk();
    $this->withHeader('Authorization', "Bearer {$adminToken}")
        ->getJson("/admin/api/users/{$user->id}/ladder/events")->assertOk();
    expect(DB::table('user_term_progress')->orderBy('term_id')->get()->toJson())->toBe($before);
});

it('404s an unknown learner', function () {
    [, $adminToken] = adminActor();

    $this->withHeader('Authorization', "Bearer {$adminToken}")
        ->getJson('/admin/api/users/' . Ulid::generate() . '/ladder')
        ->assertNotFound();

    $this->withHeader('Authorization', "Bearer {$adminToken}")
        ->getJson('/admin/api/users/not-a-ulid/ladder/events')
        ->assertNotFound();
});
