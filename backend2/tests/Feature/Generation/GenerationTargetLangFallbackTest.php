<?php

declare(strict_types=1);

use App\Modules\Generation\Application\Port\CollectionGeneratorPort;
use App\Modules\Generation\Application\Port\ImageSearchPort;
use App\Modules\Generation\Infrastructure\Adapter\FakeCollectionGenerator;
use App\Modules\Generation\Infrastructure\Adapter\FakePexelsImageSearch;
use App\Modules\Identity\Infrastructure\Eloquent\User;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->app->instance(CollectionGeneratorPort::class, new FakeCollectionGenerator());
    $this->app->instance(ImageSearchPort::class, new FakePexelsImageSearch(FakePexelsImageSearch::FOUND));
});

function fallbackUser(?string $defaultTargetLang): array
{
    $user = User::factory()->create();
    if ($defaultTargetLang !== null) {
        DB::table('profiles')->insert([
            'id' => Ulid::generate(), 'user_id' => $user->id,
            'target_language' => $defaultTargetLang, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    return [$user, $user->createToken('device')->plainTextToken];
}

it('defaults the generation target language to the profile default when omitted', function () {
    [, $token] = fallbackUser('de');

    $id = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/generations', ['prompt' => 'иду в банк', 'size' => 8])
        ->assertStatus(202)
        ->json('data.id');

    $this->assertDatabaseHas('generation_requests', ['id' => $id, 'target_lang' => 'de']);
});

it('honours an explicit target language over the profile default', function () {
    [, $token] = fallbackUser('de');

    $id = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/generations', ['prompt' => 'иду в банк', 'size' => 8, 'target_lang' => 'fr'])
        ->assertStatus(202)
        ->json('data.id');

    $this->assertDatabaseHas('generation_requests', ['id' => $id, 'target_lang' => 'fr']);
});

it('falls back to English when the user has no profile default', function () {
    [, $token] = fallbackUser(null);

    $id = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/generations', ['prompt' => 'иду в банк', 'size' => 8])
        ->assertStatus(202)
        ->json('data.id');

    $this->assertDatabaseHas('generation_requests', ['id' => $id, 'target_lang' => 'en']);
});
