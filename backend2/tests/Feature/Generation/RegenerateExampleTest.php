<?php

declare(strict_types=1);

use App\Modules\Generation\Application\Port\ExampleRegeneratorPort;
use App\Modules\Generation\Infrastructure\Adapter\FakeExampleRegenerator;
use App\Modules\Identity\Infrastructure\Eloquent\User;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->app->instance(ExampleRegeneratorPort::class, new FakeExampleRegenerator());
});

function regenUser(): array
{
    $user = User::factory()->create();

    return [$user, $user->createToken('device')->plainTextToken];
}

/** Seed an English term with a Russian translation and an existing example. */
function seedTermWithExample(): string
{
    $id = Ulid::generate();
    DB::table('terms')->insert([
        'id' => $id, 'lang' => 'en', 'text' => 'overwhelm', 'normalized_text' => 'overwhelm',
        'type' => 'word', 'source' => 'user', 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('term_translations')->insert([
        'id' => Ulid::generate(), 'term_id' => $id, 'lang' => 'ru', 'text' => 'переполнять',
        'is_primary' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    seedExample([
        'term_id' => $id, 'sentence' => 'The old example sentence.',
        'translation' => 'Старое предложение.', 'source' => 'user',
    ]);

    return $id;
}

it('regenerates the example, replacing the old one, and records the spend', function () {
    [$user, $token] = regenUser();
    $termId = seedTermWithExample();

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/terms/{$termId}/regenerate-example")
        ->assertOk()
        ->assertJsonStructure(['data' => ['example', 'example_translation']]);

    $newExample = $response->json('data.example');
    expect($newExample)->not->toBe('The old example sentence.');

    // Exactly one example row — the old one was replaced, not appended.
    $examples = DB::table('term_examples')->where('term_id', $termId)->pluck('sentence');
    expect($examples)->toHaveCount(1)
        ->and($examples->first())->toBe($newExample);

    // The regeneration was recorded (spend + quota consumption) for this user + term.
    expect(DB::table('example_regenerations')->where('user_id', $user->id)->where('term_id', $termId)->count())->toBe(1);
});

it('counts against the daily generation quota (429 once exhausted)', function () {
    [$user, $token] = regenUser();
    $termId = seedTermWithExample();

    // Free tier = 3/day. Seed 3 succeeded generations to exhaust it.
    for ($i = 0; $i < 3; $i++) {
        DB::table('generation_requests')->insert([
            'id' => Ulid::generate(), 'user_id' => $user->id, 'prompt' => 'x', 'normalized_prompt' => 'x',
            'source_lang' => 'ru', 'target_lang' => 'en', 'levels' => json_encode(['A2']), 'size' => 8,
            'prompt_version' => 'v4', 'status' => 'succeeded', 'created_at' => now(),
        ]);
    }

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/terms/{$termId}/regenerate-example")
        ->assertStatus(429)
        ->assertJsonPath('code', 'generation_quota_exceeded');

    // Nothing was spent or changed once blocked.
    expect(DB::table('example_regenerations')->where('term_id', $termId)->count())->toBe(0)
        ->and(DB::table('term_examples')->where('term_id', $termId)->value('sentence'))->toBe('The old example sentence.');
});

it('returns 404 for an unknown term', function () {
    [, $token] = regenUser();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/terms/' . Ulid::generate() . '/regenerate-example')
        ->assertNotFound();
});

it('requires authentication', function () {
    $this->postJson('/api/v1/terms/' . Ulid::generate() . '/regenerate-example')->assertUnauthorized();
});
