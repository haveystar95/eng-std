<?php

declare(strict_types=1);

use App\Modules\Shared\Domain\ValueObject\Ulid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

// learner() / seedCollectionWith() / addWordTo() / sync() live in tests/Pest.php.

it('returns a full snapshot when since is omitted', function () {
    [$user, $token] = learner();
    [$col, $money] = seedCollectionWith($user, 'money', 'деньги');

    $data = sync($this, $token);

    expect($data['has_more'])->toBeFalse()
        ->and($data['server_time'])->not->toBeNull()
        ->and(array_column($data['changes']['collections'], 'id'))->toContain($col)
        ->and(array_column($data['changes']['collection_items'], 'term_id'))->toContain($money)
        ->and(array_column($data['changes']['terms'], 'id'))->toContain($money);

    // The metro view needs text + translation offline.
    $term = collect($data['changes']['terms'])->firstWhere('id', $money);
    expect($term['op'])->toBe('upsert')
        ->and($term['text'])->toBe('money')
        ->and($term['translation'])->toBe('деньги');

    // source/type ride along so the client can render the origin badge (ИИ / my / store).
    $collection = collect($data['changes']['collections'])->firstWhere('id', $col);
    expect($collection['source'])->toBe('user')
        ->and($collection['type'])->toBe('custom');
});

it('ships image url + attribution on terms and collections when present', function () {
    [$user, $token] = learner();
    [$col, $money] = seedCollectionWith($user, 'money', 'деньги');

    DB::table('terms')->where('id', $money)->update([
        'image_url' => 'https://img/money.jpg',
        'image_author' => 'Jane Doe',
        'image_author_url' => 'https://pexels.com/@jane',
    ]);
    DB::table('collections')->where('id', $col)->update([
        'image_url' => 'https://img/cover.jpg',
        'image_author' => 'Cover Photographer',
        'image_author_url' => 'https://pexels.com/@cover',
    ]);

    $data = sync($this, $token);

    $term = collect($data['changes']['terms'])->firstWhere('id', $money);
    expect($term['image_url'])->toBe('https://img/money.jpg')
        ->and($term['image_author'])->toBe('Jane Doe')
        ->and($term['image_author_url'])->toBe('https://pexels.com/@jane')
        ->and($term)->not->toHaveKey('image_api_prompt');   // server-internal, never shipped

    $collection = collect($data['changes']['collections'])->firstWhere('id', $col);
    expect($collection['image_url'])->toBe('https://img/cover.jpg')
        ->and($collection['image_author'])->toBe('Cover Photographer')
        ->and($collection['image_author_url'])->toBe('https://pexels.com/@cover');
});

it('leaves image fields null when a term has no photo', function () {
    [$user, $token] = learner();
    [$col, $money] = seedCollectionWith($user, 'money', 'деньги');

    $data = sync($this, $token);
    $term = collect($data['changes']['terms'])->firstWhere('id', $money);

    expect($term['image_url'])->toBeNull()
        ->and($term['image_author'])->toBeNull();
});

it('returns only changes after since', function () {
    [$user, $token] = learner();
    [$colA] = seedCollectionWith($user, 'apple', 'яблоко');
    // Push colA clearly before the cursor (a caught-up client already has it). `since` is inclusive
    // (second precision → the boundary second is re-sent), so separate the seconds deterministically.
    DB::table('collections')->where('id', $colA)->update(['updated_at' => now()->subDay()]);

    $t1 = sync($this, $token)['server_time'];

    [$colB] = seedCollectionWith($user, 'bank', 'банк');       // the change after t1
    $data = sync($this, $token, 'since=' . urlencode($t1));

    expect(array_column($data['changes']['collections'], 'id'))
        ->toContain($colB)
        ->not->toContain($colA);
});

it('returns an empty delta when the client is caught up', function () {
    [$user, $token] = learner();
    [$col, $term] = seedCollectionWith($user, 'apple', 'яблоко');
    // Everything is in the past relative to the cursor → nothing at/after `since`.
    DB::table('collections')->where('id', $col)->update(['updated_at' => now()->subDay()]);
    DB::table('collection_items')->where('collection_id', $col)->update(['updated_at' => now()->subDay()]);
    DB::table('terms')->where('id', $term)->update(['updated_at' => now()->subDay()]);
    // …including the ENROLMENT. The pool is part of the feed's scope now, and a word that entered
    // it inside the window is shipped whatever its own timestamp says — so «everything is in the
    // past» has to include the moment the learner took this word into study.
    DB::table('user_term_progress')->where('term_id', $term)
        ->update(['updated_at' => now()->subDay(), 'enrolled_at' => now()->subDay()]);

    $data = sync($this, $token, 'since=' . urlencode(now()->toIso8601String()));

    expect($data['has_more'])->toBeFalse()
        ->and($data['changes']['collections'])->toBe([])
        ->and($data['changes']['collection_items'])->toBe([])
        ->and($data['changes']['terms'])->toBe([])
        ->and($data['changes']['progress'])->toBe([])
        ->and($data['changes']['triages'])->toBe([]);
});

it('ships a tombstone when a collection is deleted', function () {
    [$user, $token] = learner();
    [$col] = seedCollectionWith($user, 'apple', 'яблоко');
    $t1 = sync($this, $token)['server_time'];

    $this->withHeader('Authorization', "Bearer {$token}")
        ->deleteJson("/api/v1/collections/{$col}")->assertSuccessful();

    $data = sync($this, $token, 'since=' . urlencode($t1));
    $row = collect($data['changes']['collections'])->firstWhere('id', $col);
    expect($row)->not->toBeNull()->and($row['op'])->toBe('delete');
});

it('ships a tombstone when a term is removed from a collection', function () {
    [$user, $token] = learner();
    [$col, $apple] = seedCollectionWith($user, 'apple', 'яблоко');
    $t1 = sync($this, $token)['server_time'];

    $this->withHeader('Authorization', "Bearer {$token}")
        ->deleteJson("/api/v1/collections/{$col}/items/{$apple}")->assertSuccessful();

    $data = sync($this, $token, 'since=' . urlencode($t1));
    $row = collect($data['changes']['collection_items'])->firstWhere('term_id', $apple);
    expect($row)->not->toBeNull()->and($row['op'])->toBe('delete');
});

it('includes progress rows in the delta', function () {
    [$user, $token] = learner();
    [$col, $money] = seedCollectionWith($user, 'money', 'деньги');

    // "unsure" triage → a progress row on the ACQUISITION ladder, past the intro.
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/triage/batch', ['triages' => [[
            'id' => Ulid::generate(), 'term_id' => $money, 'verdict' => 'unsure',
            'decided_at' => now()->toIso8601String(), 'client_seq' => 1,
        ]]])->assertOk();

    $data = sync($this, $token);
    $p = collect($data['changes']['progress'])->firstWhere('term_id', $money);
    // Both dimensions ride the delta: the device mirrors LearningLadder to decide what a card
    // should be while offline, and `state` alone would only tell it WHEN the word is due.
    expect($p)->not->toBeNull()
        ->and($p['op'])->toBe('upsert')
        ->and($p['state'])->toBe('new')            // the scheduler has never seen it
        ->and($p['acquisition'])->toBe('learning') // …but the ladder has: rung 1, intro skipped
        ->and($p['learning_step'])->toBe(1);
});

it('carries the trainer toggles and the admission matrix as settings on every page', function () {
    [, $token] = learner();

    $data = sync($this, $token);

    expect($data['settings']['exercise_modes'])->toBe(config('learning.enabled_modes'));

    $byMode = collect($data['settings']['mode_admission'])->keyBy('mode');
    // The rungs the device needs to build a session offline: which trainer opens where.
    expect($byMode->has('intro'))->toBeTrue()
        ->and($byMode['intro']['min_step'])->toBe(0)
        ->and($byMode['multiple_choice']['min_step'])->toBe(1)
        ->and($byMode['multiple_choice']['options_policy'])->toBe('distant')
        ->and($byMode['word_bank']['min_step'])->toBe(3)
        ->and($byMode['typing']['min_step'])->toBe(4)
        ->and($byMode['dictation']['min_step'])->toBe(5);
});

it('carries triage verdicts so a re-login cannot resurrect an unknown swipe', function () {
    [$user, $token] = learner();
    [$col, $money] = seedCollectionWith($user, 'money', 'деньги');

    // `unknown` enrols the pair at rung 0 AND appends to the term_triages log. Both halves have to
    // reach the device: the progress row is what «Мои слова» and the trainer read, and the triage
    // marker is what stops the deck re-offering the word after a sign-out wiped the local one.
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/triage/batch', ['triages' => [[
            'id' => Ulid::generate(), 'term_id' => $money, 'verdict' => 'unknown',
            'collection_id' => $col,
            'decided_at' => now()->toIso8601String(), 'client_seq' => 1,
        ]]])->assertOk();

    $data = sync($this, $token);

    $p = collect($data['changes']['progress'])->firstWhere('term_id', $money);
    expect($p)->not->toBeNull()
        ->and($p['acquisition'])->toBe('new')       // rung 0 — never shown
        ->and($p['enrolled_at'])->not->toBeNull();  // …and in the pool, which is what the swipe meant

    $t = collect($data['changes']['triages'])->firstWhere('term_id', $money);
    expect($t)->not->toBeNull()
        ->and($t['op'])->toBe('upsert')
        ->and($t['verdict'])->toBe('unknown')
        ->and($t['client_seq'])->toBe(1)
        ->and($t['collection_id'])->toBe($col);
});

it('ships only the governing (greatest client_seq) triage verdict per term', function () {
    [$user, $token] = learner();
    [$col, $money] = seedCollectionWith($user, 'money', 'деньги');

    $swipe = fn (string $verdict, int $seq) => $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/triage/batch', ['triages' => [[
            'id' => Ulid::generate(), 'term_id' => $money, 'verdict' => $verdict,
            'decided_at' => now()->toIso8601String(), 'client_seq' => $seq,
        ]]])->assertOk();

    $swipe('unknown', 1);
    $swipe('unsure', 2);   // later swipe governs

    $rows = collect(sync($this, $token)['changes']['triages'])->where('term_id', $money)->values();
    expect($rows)->toHaveCount(1)
        ->and($rows[0]['verdict'])->toBe('unsure')
        ->and($rows[0]['client_seq'])->toBe(2);
});

it('windows triages by since — a past swipe is skipped, a re-triage after the cursor reappears', function () {
    [$user, $token] = learner();
    [$col, $money] = seedCollectionWith($user, 'money', 'деньги');

    $swipe = fn (string $verdict, int $seq) => $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/triage/batch', ['triages' => [[
            'id' => Ulid::generate(), 'term_id' => $money, 'verdict' => $verdict,
            'decided_at' => now()->toIso8601String(), 'client_seq' => $seq,
        ]]])->assertOk();

    $swipe('unknown', 1);
    // Push the governing row clearly before the cursor (a caught-up client already has it).
    DB::table('term_triages')->where('term_id', $money)->update(['created_at' => now()->subDay()]);

    $t1 = sync($this, $token)['server_time'];

    // Caught up → nothing at/after t1.
    expect(sync($this, $token, 'since=' . urlencode($t1))['changes']['triages'])->toBe([]);

    // Re-triage now → the governing row's receipt time is after t1, so it reappears with the new verdict.
    $swipe('known', 2);
    $t = collect(sync($this, $token, 'since=' . urlencode($t1))['changes']['triages'])->firstWhere('term_id', $money);
    expect($t)->not->toBeNull()->and($t['verdict'])->toBe('known')->and($t['client_seq'])->toBe(2);
});

it('paginates with next_cursor until has_more is false', function () {
    [$user, $token] = learner();
    [$col] = seedCollectionWith($user, 'w0', 'п0');
    for ($i = 1; $i <= 5; $i++) {
        addWordTo($col, $user->id, "w{$i}", "п{$i}");        // 6 items + 6 terms + 1 collection = 13 changes
    }

    $collections = [];
    $items = [];
    $terms = [];
    $query = 'limit=3';
    $pages = 0;
    do {
        $data = sync($this, $token, $query);
        $collections = array_merge($collections, array_column($data['changes']['collections'], 'id'));
        $items = array_merge($items, array_column($data['changes']['collection_items'], 'term_id'));
        $terms = array_merge($terms, array_column($data['changes']['terms'], 'id'));
        $query = 'limit=3&cursor=' . urlencode((string) $data['next_cursor']);
        $pages++;
    } while ($data['has_more'] && $pages < 20);

    expect($data['has_more'])->toBeFalse()
        ->and($pages)->toBeGreaterThan(1)                    // actually paged
        ->and($collections)->toContain($col)
        ->and(count($terms))->toBe(6)
        ->and(count($items))->toBe(6);
});

/**
 * THE POOL IS PART OF THE FEED'S SCOPE, whatever became of the folders its words came from.
 *
 * The scope used to be the learner's live collections and nothing else, and the pool is not a
 * collection: a word stays in the trainer when its folder is deleted, and it can enter the pool with
 * no folder at all. So the feed shipped such a word's PROGRESS and not its CONTENT — the phone held
 * a queued pair it could not draw, the word was missing from «Мои слова», and the next full snapshot
 * (every pull-to-refresh is one) reaped it, while the server went on dealing it in sessions.
 * Reported from the device on an Italian word whose collection had been deleted.
 */
it('keeps an orphaned pool word in the snapshot after its collection is deleted', function () {
    [$user, $token] = learner();
    [$col, $grazie] = seedCollectionWith($user, 'grazie', 'спасибо');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->deleteJson("/api/v1/collections/{$col}")->assertSuccessful();

    // A FULL snapshot — what pull-to-refresh asks for, and what the client reconciles against by
    // reaping every local row the snapshot does not name.
    $data = sync($this, $token);

    $term = collect($data['changes']['terms'])->firstWhere('id', $grazie);
    expect($term)->not->toBeNull()
        ->and($term['op'])->toBe('upsert')
        // …with the content the word card and «Мои слова» are drawn from, not just an id.
        ->and($term['text'])->toBe('grazie')
        ->and($term['translation'])->toBe('спасибо');

    // …and its progress, so the row that says «this is in the trainer» survives with it.
    $progress = collect($data['changes']['progress'])->firstWhere('term_id', $grazie);
    expect($progress)->not->toBeNull()
        ->and($progress['enrolled_at'])->not->toBeNull();
});

it('does not disturb a word that still lives in another collection', function () {
    [$user, $token] = learner();
    [$doomed, $grazie] = seedCollectionWith($user, 'grazie', 'спасибо');
    $kept = adminSeedTerm($user, 'Italiano', 'ciao', 'привет');
    // The SAME term in a second folder — terms are globally deduplicated, so this is one word.
    $again = addWordTo($kept[0], $user->id, 'grazie', 'спасибо');
    expect($again)->toBe($grazie);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->deleteJson("/api/v1/collections/{$doomed}")->assertSuccessful();

    $data = sync($this, $token);

    $memberships = collect($data['changes']['collection_items'])->where('term_id', $grazie);

    expect(array_column($data['changes']['terms'], 'id'))->toContain($grazie)
        // The surviving membership rides as an ordinary upsert, and the word keeps its place in the
        // trainer. What the DELETED folder's own rows say is Collections' business and is left
        // exactly as it was — this change adds ids to the term scope and touches nothing else.
        ->and($memberships->firstWhere('collection_id', $kept[0])['op'])->toBe('upsert')
        ->and(collect($data['changes']['progress'])->firstWhere('term_id', $grazie)['enrolled_at'])
        ->not->toBeNull();
});

it('ships a word taken into study with no collection at all', function () {
    [$user, $token] = learner();
    // «Учить это слово» straight from search: the word reaches the pool without a folder. Modelled
    // as the term plus the enrolment, which is exactly what that door writes.
    $termId = seedWordFor($user, 'grazie', 'спасибо', enroll: false);
    DB::table('collection_items')->where('term_id', $termId)->delete();
    enrollTerm($user, $termId);

    $data = sync($this, $token);

    $term = collect($data['changes']['terms'])->firstWhere('id', $termId);
    expect($term)->not->toBeNull()->and($term['text'])->toBe('grazie');
});

it('carries an orphan into an INCREMENTAL delta, though enrolment never touches the term', function () {
    [$user, $token] = learner();
    $termId = seedWordFor($user, 'grazie', 'спасибо', enroll: false);
    DB::table('collection_items')->where('term_id', $termId)->delete();
    // An OLD word: its own updated_at is far outside the window, so the changed-terms query cannot
    // reach it. This is the case a scope fix ALONE would still have missed — enrolment writes
    // `enrolled_at` and nothing on the term, so the window has to be asked about the enrolment.
    DB::table('terms')->where('id', $termId)->update(['updated_at' => now()->subYear()]);

    $t1 = sync($this, $token)['server_time'];
    enrollTerm($user, $termId);

    $data = sync($this, $token, 'since=' . urlencode($t1));

    $term = collect($data['changes']['terms'])->firstWhere('id', $termId);
    expect($term)->not->toBeNull()
        ->and($term['op'])->toBe('upsert')
        ->and($term['text'])->toBe('grazie');
});
