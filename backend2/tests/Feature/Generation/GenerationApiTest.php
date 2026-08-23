<?php

declare(strict_types=1);

use App\Modules\Generation\Application\Command\RequestCollectionGeneration;
use App\Modules\Generation\Application\Command\RequestCollectionGenerationHandler;
use App\Modules\Generation\Application\Port\CollectionGeneratorPort;
use App\Modules\Generation\Infrastructure\Adapter\FakeCollectionGenerator;
use App\Modules\Identity\Infrastructure\Eloquent\User;
use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    // No network, no key: bind the deterministic generator. The sync queue runs the job inline.
    $this->app->instance(CollectionGeneratorPort::class, new FakeCollectionGenerator());
});

function bearerToken(): string
{
    return User::factory()->create()->createToken('test-device')->plainTextToken;
}

it('accepts a generation and completes it end-to-end on the sync queue', function () {
    $token = bearerToken();

    $created = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/generations', ['prompt' => 'иду в банк', 'size' => 8]);

    $created->assertStatus(202)->assertJsonStructure(['data' => ['id', 'status', 'prompt']]);
    $id = $created->json('data.id');

    // QA-OBS-9: asked 8, the overshoot generates ceil(8*1.3)=11, and the surplus is spare — the
    // learner gets the 8 they ordered, and the other 3 are never written.
    $shown = $this->withHeader('Authorization', "Bearer {$token}")->getJson("/api/v1/generations/{$id}");
    $shown->assertOk()
        ->assertJsonPath('data.status', 'succeeded')
        ->assertJsonPath('data.requested', 8)   // honest requested/delivered surfaced for the client
        ->assertJsonPath('data.delivered', 8);
    expect($shown->json('data.collection_id'))->not->toBeNull();

    $this->assertDatabaseHas('generation_requests', ['id' => $id, 'status' => 'succeeded']);
    $this->assertDatabaseHas('collections', ['source' => 'ai', 'source_lang' => 'ru', 'target_lang' => 'en']);
    $this->assertDatabaseCount('collection_items', 8);
    $this->assertDatabaseCount('terms', 8);

    // Generated terms carry pronunciation and a usage example (persisted, not dropped).
    expect(DB::table('terms')->whereNotNull('ipa')->count())->toBe(8);
    $this->assertDatabaseCount('term_examples', 8);
    $this->assertDatabaseHas('term_examples', ['sentence_translation' => 'Это образец предложения номер 1.']);
});

it('delivers exactly the requested count when the overshoot brings back more (QA-OBS-9)', function () {
    $token = bearerToken();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/generations', ['prompt' => 'путешествие', 'size' => 15])
        ->assertStatus(202);

    // The overshoot generates ceil(15*1.3)=20 so that 15 can survive validation and the barrier.
    // The 5 that were not needed are spare, and spare is not written.
    $this->assertDatabaseCount('terms', 15);
});

it('validates that a prompt is required', function () {
    $this->withHeader('Authorization', 'Bearer ' . bearerToken())
        ->postJson('/api/v1/generations', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors('prompt');
});

it('requires authentication', function () {
    $this->postJson('/api/v1/generations', ['prompt' => 'иду в банк'])->assertUnauthorized();
});

it('returns 404 for an unknown generation id', function () {
    $this->withHeader('Authorization', 'Bearer ' . bearerToken())
        ->getJson('/api/v1/generations/' . Ulid::generate())
        ->assertNotFound();
});

it("hides another user's generation behind 404", function () {
    // Seed a generation owned by someone else (no HTTP), then GET it as a different user.
    // One authenticated request per test avoids the guard caching the first user.
    $owner = User::factory()->create();
    $id = app(RequestCollectionGenerationHandler::class)(new RequestCollectionGeneration(
        UserId::fromString($owner->id), 'секрет', new LanguageCode('ru'), new LanguageCode('en'), ['A2'], 8,
    ))->id;

    $this->withHeader('Authorization', 'Bearer ' . bearerToken())
        ->getJson("/api/v1/generations/{$id->value}")
        ->assertNotFound();
});
