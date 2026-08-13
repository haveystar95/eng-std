<?php

declare(strict_types=1);

use App\Modules\Shared\Domain\ValueObject\Ulid;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// learner() / seedCollectionWith() / addWordTo() live in tests/Pest.php.

it('projects triage verdicts onto progress and never writes reviews', function () {
    [$user, $token] = learner();
    [$col, $money] = seedCollectionWith($user, 'money', 'деньги');
    $withdraw = addWordTo($col, $user->id, 'withdraw cash', 'снять наличные');
    $overdraft = addWordTo($col, $user->id, 'overdraft', 'овердрафт');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/triage/batch', ['triages' => [
            ['id' => Ulid::generate(), 'term_id' => $money, 'verdict' => 'known', 'collection_id' => $col, 'decided_at' => now()->toIso8601String(), 'client_seq' => 1],
            ['id' => Ulid::generate(), 'term_id' => $withdraw, 'verdict' => 'unsure', 'collection_id' => $col, 'decided_at' => now()->toIso8601String(), 'client_seq' => 2],
            ['id' => Ulid::generate(), 'term_id' => $overdraft, 'verdict' => 'unknown', 'collection_id' => $col, 'decided_at' => now()->toIso8601String(), 'client_seq' => 3],
        ]])
        ->assertOk()
        ->assertJsonPath('data.accepted', 3)
        ->assertJsonPath('data.unknown', 0);

    $this->assertDatabaseHas('user_term_progress', ['term_id' => $money, 'state' => 'known']);
    $this->assertDatabaseHas('user_term_progress', ['term_id' => $withdraw, 'state' => 'learning']);
    $this->assertDatabaseMissing('user_term_progress', ['term_id' => $overdraft]);
    $this->assertDatabaseCount('reviews', 0);
});

it('persists revealed and brings a revealed «known» check far sooner than a clean one', function () {
    [$user, $token] = learner();
    [$col, $peeked] = seedCollectionWith($user, 'money', 'деньги');
    $clean = addWordTo($col, $user->id, 'overdraft', 'овердрафт');

    // Both below level / no latency → the only difference is the peek.
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/triage/batch', ['triages' => [
            ['id' => Ulid::generate(), 'term_id' => $peeked, 'verdict' => 'known', 'collection_id' => $col, 'decided_at' => now()->toIso8601String(), 'client_seq' => 1, 'revealed' => true],
            ['id' => Ulid::generate(), 'term_id' => $clean, 'verdict' => 'known', 'collection_id' => $col, 'decided_at' => now()->toIso8601String(), 'client_seq' => 2, 'revealed' => false],
        ]])
        ->assertOk()
        ->assertJsonPath('data.accepted', 2);

    // The flip flag is recorded on the append-only log…
    $this->assertDatabaseHas('term_triages', ['term_id' => $peeked, 'revealed' => true]);
    $this->assertDatabaseHas('term_triages', ['term_id' => $clean, 'revealed' => false]);

    // …and it pulled the peeked term's verification check in (early ~7d vs the trusted ~90d).
    $peekedDue = \Illuminate\Support\Facades\DB::table('user_term_progress')->where('term_id', $peeked)->value('due_at');
    $cleanDue = \Illuminate\Support\Facades\DB::table('user_term_progress')->where('term_id', $clean)->value('due_at');
    expect(new DateTimeImmutable((string) $peekedDue))->toBeLessThan(new DateTimeImmutable((string) $cleanDue));
});

it('ignores a re-uploaded triage batch', function () {
    [$user, $token] = learner();
    [, $money] = seedCollectionWith($user, 'money', 'деньги');
    $batch = ['triages' => [[
        'id' => Ulid::generate(), 'term_id' => $money, 'verdict' => 'known', 'decided_at' => now()->toIso8601String(), 'client_seq' => 1,
    ]]];

    $this->withHeader('Authorization', "Bearer {$token}")->postJson('/api/v1/triage/batch', $batch)->assertOk();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/triage/batch', $batch)
        ->assertOk()
        ->assertJsonPath('data.accepted', 0)
        ->assertJsonPath('data.duplicates', 1);
});

it('excludes studied and triaged terms from the triage queue', function () {
    [$user, $token] = learner();
    [$col, $apple] = seedCollectionWith($user, 'apple', 'яблоко');
    $bank = addWordTo($col, $user->id, 'bank', 'банк');
    $cherry = addWordTo($col, $user->id, 'cherry', 'вишня');

    // apple is triaged (stays new, but must not be re-asked); bank is studied (has a row).
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/triage/batch', ['triages' => [
            ['id' => Ulid::generate(), 'term_id' => $apple, 'verdict' => 'unknown', 'decided_at' => now()->toIso8601String(), 'client_seq' => 1],
        ]])->assertOk();
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/reviews/batch', ['reviews' => [
            ['id' => Ulid::generate(), 'term_id' => $bank, 'exercise_mode' => 'typing', 'response' => 'bank', 'answered_at' => now()->toIso8601String(), 'client_seq' => 1],
        ]])->assertOk();

    $data = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/triage/queue?collection_id=' . $col)
        ->assertOk()
        ->assertJsonPath('data.remaining', 0)   // cherry is the only eligible term → nothing beyond this page
        ->json('data.cards');

    expect(array_column($data, 'term_id'))->toBe([$cherry]);
});

it('caps the queue page and reports the eligible remainder', function () {
    [$user, $token] = learner();
    [$col] = seedCollectionWith($user, 'w0', 'п0');
    for ($i = 1; $i < 45; $i++) {          // 45 eligible terms total (w0..w44)
        addWordTo($col, $user->id, "w{$i}", "п{$i}");
    }

    // Page 1: capped at the default limit of 40, with 5 left beyond it.
    $page1 = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/triage/queue?collection_id=' . $col)
        ->assertOk()
        ->assertJsonCount(40, 'data.cards')
        ->assertJsonPath('data.remaining', 5)
        ->json('data.cards');

    // Triage the whole first page, then the next GET returns exactly the remainder, remaining 0.
    $triages = array_map(fn (array $c): array => [
        'id' => Ulid::generate(), 'term_id' => $c['term_id'], 'verdict' => 'unknown',
        'decided_at' => now()->toIso8601String(), 'client_seq' => 1,
    ], $page1);
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/triage/batch', ['triages' => $triages])->assertOk();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/triage/queue?collection_id=' . $col)
        ->assertOk()
        ->assertJsonCount(5, 'data.cards')
        ->assertJsonPath('data.remaining', 0);
});

it('keeps a known term out of study and returns it to new when triaged unknown', function () {
    [$user, $token] = learner();
    [$col, $money] = seedCollectionWith($user, 'money', 'деньги');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/triage/batch', ['triages' => [
            ['id' => Ulid::generate(), 'term_id' => $money, 'verdict' => 'known', 'decided_at' => now()->toIso8601String(), 'client_seq' => 1],
        ]])->assertOk();

    // known → no due, not new → nothing to study.
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/study/sessions', ['collection_id' => $col])
        ->assertOk()
        ->assertJsonPath('data.cards', []);

    // return to learning: resets the row to new → shows up in study again.
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/triage/batch', ['triages' => [
            ['id' => Ulid::generate(), 'term_id' => $money, 'verdict' => 'unknown', 'decided_at' => now()->addSecond()->toIso8601String(), 'client_seq' => 2],
        ]])->assertOk();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/study/sessions', ['collection_id' => $col])
        ->assertOk()
        ->assertJsonCount(1, 'data.cards')
        ->assertJsonPath('data.cards.0.term_id', $money)
        ->assertJsonPath('data.cards.0.exercise_mode', 'multiple_choice');
});

it('does not resolve a known-term verification in a practice session', function () {
    [$user, $token] = learner();
    [, $money] = seedCollectionWith($user, 'money', 'деньги');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/triage/batch', ['triages' => [
            ['id' => Ulid::generate(), 'term_id' => $money, 'verdict' => 'known', 'decided_at' => now()->toIso8601String(), 'client_seq' => 1],
        ]])->assertOk();

    // A practice answer (even a wrong one) to the known term: recorded, but it must not fail the
    // check or crash the fold — practice is dropped before scheduling / verification resolution.
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/reviews/batch', ['reviews' => [[
            'id' => Ulid::generate(), 'term_id' => $money, 'exercise_mode' => 'typing', 'response' => 'wrong',
            'answered_at' => now()->toIso8601String(), 'is_practice' => true, 'client_seq' => 1,
        ]]])
        ->assertOk()
        ->assertJsonPath('data.accepted', 1);

    $this->assertDatabaseHas('reviews', ['term_id' => $money, 'is_practice' => true, 'is_verification' => false]);
    $this->assertDatabaseHas('user_term_progress', ['term_id' => $money, 'state' => 'known']);
});

it('clears the verification due_at when a known term is returned to new', function () {
    [$user, $token] = learner();
    [, $money] = seedCollectionWith($user, 'money', 'деньги');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/triage/batch', ['triages' => [
            ['id' => Ulid::generate(), 'term_id' => $money, 'verdict' => 'known', 'decided_at' => now()->toIso8601String(), 'client_seq' => 1],
        ]])->assertOk();

    // Return to learning: state resets to new and the scheduled check is dropped, so the selector
    // gives it the intro mode, not a forced typing verification.
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/triage/batch', ['triages' => [
            ['id' => Ulid::generate(), 'term_id' => $money, 'verdict' => 'unknown', 'decided_at' => now()->addSecond()->toIso8601String(), 'client_seq' => 2],
        ]])->assertOk();

    $this->assertDatabaseHas('user_term_progress', ['term_id' => $money, 'state' => 'new', 'due_at' => null]);
});

it('validates the triage batch', function () {
    [, $token] = learner();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/triage/batch', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors('triages');
});

it('requires a collection id for the triage queue', function () {
    [, $token] = learner();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/triage/queue')
        ->assertStatus(422)
        ->assertJsonValidationErrors('collection_id');
});
