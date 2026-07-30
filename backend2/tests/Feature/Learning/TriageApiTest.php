<?php

declare(strict_types=1);

use App\Modules\Collections\Application\Command\AddWordToCollection;
use App\Modules\Collections\Application\Command\AddWordToCollectionHandler;
use App\Modules\Shared\Domain\ValueObject\CollectionId;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** Add another word to an existing collection; returns its term id. (learner()/seedCollectionWith() live in StudyApiTest.) */
function addWordTo(string $collectionId, string $userId, string $text, string $translation = 'x'): string
{
    return app(AddWordToCollectionHandler::class)(new AddWordToCollection(
        CollectionId::fromString($collectionId), UserId::fromString($userId), $text, $translation,
    ))->value;
}

it('projects triage verdicts onto progress and never writes reviews', function () {
    [$user, $token] = learner();
    [$col, $money] = seedCollectionWith($user, 'money', 'деньги');
    $withdraw = addWordTo($col, $user->id, 'withdraw cash', 'снять наличные');
    $overdraft = addWordTo($col, $user->id, 'overdraft', 'овердрафт');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/triage/batch', ['triages' => [
            ['id' => Ulid::generate(), 'term_id' => $money, 'verdict' => 'known', 'collection_id' => $col, 'decided_at' => now()->toIso8601String()],
            ['id' => Ulid::generate(), 'term_id' => $withdraw, 'verdict' => 'unsure', 'collection_id' => $col, 'decided_at' => now()->toIso8601String()],
            ['id' => Ulid::generate(), 'term_id' => $overdraft, 'verdict' => 'unknown', 'collection_id' => $col, 'decided_at' => now()->toIso8601String()],
        ]])
        ->assertOk()
        ->assertJsonPath('data.accepted', 3)
        ->assertJsonPath('data.unknown', 0);

    $this->assertDatabaseHas('user_term_progress', ['term_id' => $money, 'state' => 'known']);
    $this->assertDatabaseHas('user_term_progress', ['term_id' => $withdraw, 'state' => 'learning']);
    $this->assertDatabaseMissing('user_term_progress', ['term_id' => $overdraft]);
    $this->assertDatabaseCount('reviews', 0);
});

it('ignores a re-uploaded triage batch', function () {
    [$user, $token] = learner();
    [, $money] = seedCollectionWith($user, 'money', 'деньги');
    $batch = ['triages' => [[
        'id' => Ulid::generate(), 'term_id' => $money, 'verdict' => 'known', 'decided_at' => now()->toIso8601String(),
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
            ['id' => Ulid::generate(), 'term_id' => $apple, 'verdict' => 'unknown', 'decided_at' => now()->toIso8601String()],
        ]])->assertOk();
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/reviews/batch', ['reviews' => [
            ['id' => Ulid::generate(), 'term_id' => $bank, 'grade' => 'good', 'answered_at' => now()->toIso8601String()],
        ]])->assertOk();

    $data = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/triage/queue?collection_id=' . $col)
        ->assertOk()
        ->json('data');

    expect(array_column($data, 'term_id'))->toBe([$cherry]);
});

it('keeps a known term out of study and returns it to new when triaged unknown', function () {
    [$user, $token] = learner();
    [$col, $money] = seedCollectionWith($user, 'money', 'деньги');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/triage/batch', ['triages' => [
            ['id' => Ulid::generate(), 'term_id' => $money, 'verdict' => 'known', 'decided_at' => now()->toIso8601String()],
        ]])->assertOk();

    // known → no due, not new → nothing to study.
    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/study/due?collection_id=' . $col)
        ->assertOk()
        ->assertJsonPath('data', []);

    // return to learning: drops the row → new again → shows up in study.
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/triage/batch', ['triages' => [
            ['id' => Ulid::generate(), 'term_id' => $money, 'verdict' => 'unknown', 'decided_at' => now()->addSecond()->toIso8601String()],
        ]])->assertOk();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/study/due?collection_id=' . $col)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.term_id', $money)
        ->assertJsonPath('data.0.state', 'new');
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
