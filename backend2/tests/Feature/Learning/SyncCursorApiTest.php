<?php

declare(strict_types=1);

use App\Modules\Shared\Domain\ValueObject\Ulid;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// learner() / seedCollectionWith() live in StudyApiTest and are shared across the suite.

it('reports a zero cursor when nothing is logged yet', function () {
    [, $token] = learner();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/sync/cursor')
        ->assertOk()
        ->assertJsonPath('data.max_triage_seq', 0)
        ->assertJsonPath('data.max_review_seq', 0);
});

it('reports the greatest client_seq per log', function () {
    [$user, $token] = learner();
    [$col, $money] = seedCollectionWith($user, 'money', 'деньги');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/triage/batch', ['triages' => [
            ['id' => Ulid::generate(), 'term_id' => $money, 'verdict' => 'unknown', 'collection_id' => $col, 'decided_at' => now()->toIso8601String(), 'client_seq' => 7],
        ]])->assertOk();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/reviews/batch', ['reviews' => [
            ['id' => Ulid::generate(), 'term_id' => $money, 'exercise_mode' => 'typing', 'response' => 'money', 'answered_at' => now()->toIso8601String(), 'client_seq' => 4],
        ]])->assertOk();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/sync/cursor')
        ->assertOk()
        ->assertJsonPath('data.max_triage_seq', 7)
        ->assertJsonPath('data.max_review_seq', 4);
});
