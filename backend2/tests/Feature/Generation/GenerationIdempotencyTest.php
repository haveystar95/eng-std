<?php

declare(strict_types=1);

use App\Modules\Generation\Application\Command\RequestCollectionGeneration;
use App\Modules\Generation\Application\Command\RequestCollectionGenerationHandler;
use App\Modules\Generation\Application\Port\CollectionGeneratorPort;
use App\Modules\Generation\Application\Port\ImageSearchPort;
use App\Modules\Generation\Domain\Exception\GenerationIdConflict;
use App\Modules\Generation\Infrastructure\Adapter\FakeCollectionGenerator;
use App\Modules\Generation\Infrastructure\Adapter\FakePexelsImageSearch;
use App\Modules\Identity\Infrastructure\Eloquent\User;
use App\Modules\Shared\Domain\ValueObject\GenerationRequestId;
use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->app->instance(CollectionGeneratorPort::class, new FakeCollectionGenerator());
    $this->app->instance(ImageSearchPort::class, new FakePexelsImageSearch(FakePexelsImageSearch::FOUND));
});

function idempotencyToken(): string
{
    return User::factory()->create()->createToken('device')->plainTextToken;
}

it('accepts a client-generated id and echoes it back (202)', function () {
    $token = idempotencyToken();
    $clientId = Ulid::generate();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/generations', ['id' => $clientId, 'prompt' => 'иду в банк', 'size' => 8])
        ->assertStatus(202)
        ->assertJsonPath('data.id', $clientId);

    $this->assertDatabaseHas('generation_requests', ['id' => $clientId]);
});

it('is idempotent on a repeated client id — returns the existing request (200), no duplicate', function () {
    $token = idempotencyToken();
    $clientId = Ulid::generate();
    $body = ['id' => $clientId, 'prompt' => 'иду в банк', 'size' => 8];

    $this->withHeader('Authorization', "Bearer {$token}")->postJson('/api/v1/generations', $body)->assertStatus(202);

    // Re-send the same id (offline retry) → 200 with the SAME request, not a new one, not an error.
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/generations', $body)
        ->assertStatus(200)
        ->assertJsonPath('data.id', $clientId);

    // Exactly one row and one unit of quota spent.
    expect(DB::table('generation_requests')->where('id', $clientId)->count())->toBe(1)
        ->and(DB::table('generation_requests')->count())->toBe(1);
});

it('rejects a client id already owned by another user (409)', function () {
    // Exercised at the handler level: two authenticated HTTP requests in one test process share
    // Laravel's cached auth guard, so the cross-user case can't be driven over HTTP here. The
    // controller maps GenerationIdConflict → 409 via the ProblemDetails renderer.
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $clientId = GenerationRequestId::generate();

    $make = fn (User $user, string $prompt) => new RequestCollectionGeneration(
        userId: UserId::fromString($user->id),
        prompt: $prompt,
        sourceLang: new LanguageCode('ru'),
        targetLang: new LanguageCode('en'),
        levels: ['A2'],
        size: 8,
        id: $clientId,
    );

    app(RequestCollectionGenerationHandler::class)($make($userA, 'иду в банк'));

    expect(fn () => app(RequestCollectionGenerationHandler::class)($make($userB, 'другое')))
        ->toThrow(GenerationIdConflict::class);
});

it('still works without a client id (server generates one)', function () {
    $token = idempotencyToken();

    $id = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/generations', ['prompt' => 'иду в банк', 'size' => 8])
        ->assertStatus(202)
        ->json('data.id');

    expect($id)->not->toBeNull();
    $this->assertDatabaseHas('generation_requests', ['id' => $id]);
});
