<?php

declare(strict_types=1);

use App\Modules\Collections\Application\Command\AddWordToCollection;
use App\Modules\Collections\Application\Command\AddWordToCollectionHandler;
use App\Modules\Collections\Application\Command\CreateCustomCollection;
use App\Modules\Collections\Application\Command\CreateCustomCollectionHandler;
use App\Modules\Generation\Application\Port\DialogSummarizerPort;
use App\Modules\Generation\Application\Port\RealtimeTokenPort;
use App\Modules\Generation\Domain\Service\PracticeDailyLimit;
use App\Modules\Generation\Infrastructure\Adapter\FakeDialogSummarizer;
use App\Modules\Generation\Infrastructure\Adapter\FakeRealtimeTokenMinter;
use App\Modules\Generation\Infrastructure\Adapter\GeminiLiveTokenMinter;
use App\Modules\Generation\Infrastructure\Prompt\PracticeDialogInstructions;
use Illuminate\Support\Facades\Http;
use App\Modules\Identity\Infrastructure\Eloquent\Profile;
use App\Modules\Identity\Infrastructure\Eloquent\User;
use App\Modules\Shared\Domain\Service\Clock;
use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Doubles\FixedClock;

uses(RefreshDatabase::class);

const PRACTICE_NOW = '2026-08-07T10:00:00+00:00';

beforeEach(function () {
    // Pin the driver so the baseline doesn't depend on the operator's .env (which may be switched
    // to gemini for device testing); the gemini-specific tests override it themselves.
    config(['services.practice.driver' => 'openai']);
    // Deterministic time so the token TTL (and the day window) are exact and assertable.
    $this->app->instance(Clock::class, new FixedClock(new DateTimeImmutable(PRACTICE_NOW)));
    $this->app->bind(RealtimeTokenPort::class, fn ($app) => new FakeRealtimeTokenMinter($app->make(Clock::class)));
    $this->app->instance(DialogSummarizerPort::class, new FakeDialogSummarizer());
});

/** @return array{0: User, 1: string} A premium user + a device token. */
function premiumLearner(string $cefr = 'B1'): array
{
    $user = User::factory()->create();
    Profile::create([
        'user_id' => $user->id, 'native_language' => 'ru', 'target_language' => 'en',
        'cefr_level' => $cefr, 'daily_goal' => 20,
    ]);
    DB::table('profiles')->where('user_id', $user->id)->update(['tier' => 'premium']);

    return [$user, $user->createToken('device')->plainTextToken];
}

/** A ru→en collection with the given target words. Returns the collection id. */
function seedPracticeCollection(User $user, array $words = ['withdraw cash', 'account balance', 'exchange rate']): string
{
    $actor = UserId::fromString($user->id);
    $collectionId = app(CreateCustomCollectionHandler::class)(new CreateCustomCollection(
        $actor, 'At the bank', new LanguageCode('ru'), new LanguageCode('en'),
    ));
    foreach ($words as $word) {
        app(AddWordToCollectionHandler::class)(new AddWordToCollection($collectionId, $actor, $word, 'x'));
    }

    return $collectionId->value;
}

/** A store deck (system, owner NULL) with target terms + a subscription for $user. Returns its id. */
function subscribedStoreDeck(User $user, bool $active = true): string
{
    $cid = Ulid::generate();
    DB::table('collections')->insert([
        'id' => $cid, 'owner_id' => null, 'type' => 'system', 'title' => 'At the airport',
        'source_lang' => 'ru', 'target_lang' => 'en', 'visibility' => 'public', 'source' => 'curated',
        'items_count' => 2, 'is_premium' => false, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $pos = 0;
    foreach (['passport' => 'паспорт', 'boarding pass' => 'посадочный'] as $text => $ru) {
        $tid = Ulid::generate();
        DB::table('terms')->insert([
            'id' => $tid, 'lang' => 'en', 'text' => $text, 'normalized_text' => $text, 'type' => 'word',
            'source' => 'curated', 'cefr' => 'A2', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('term_translations')->insert([
            'id' => Ulid::generate(), 'term_id' => $tid, 'lang' => 'ru', 'text' => $ru, 'is_primary' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('collection_items')->insert([
            'id' => Ulid::generate(), 'collection_id' => $cid, 'term_id' => $tid, 'position' => $pos++,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
    DB::table('user_collections')->insert([
        'user_id' => $user->id, 'collection_id' => $cid, 'added_at' => now(),
        'unsubscribed_at' => $active ? null : now(), 'is_pinned' => false,
    ]);

    return $cid;
}

it('starts a dialog for a premium user with a token, target words and TTL', function () {
    [$user, $token] = premiumLearner();
    $collectionId = seedPracticeCollection($user);
    $clientId = Ulid::generate();

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/practice/dialogs', ['collection_id' => $collectionId, 'client_id' => $clientId])
        ->assertCreated()
        ->assertJsonPath('dialog_id', $clientId)
        ->assertJsonPath('model', 'gpt-realtime-2.1-mini')
        ->assertJsonPath('provider', 'openai')
        ->assertJsonPath('endpoint', 'https://fake.realtime.local')
        ->assertJsonPath('duration_seconds', 200)
        // TTL reached the mint: expiry = fixed now + 200s.
        ->assertJsonPath('expires_at', '2026-08-07T10:03:20+00:00');

    expect($response->json('realtime_token'))->toStartWith('fake-ephemeral-');

    $words = $response->json('target_words');
    expect($words)->toHaveCount(3)
        ->and($words[0]['used'])->toBeFalse();

    $this->assertDatabaseHas('practice_dialogs', [
        'id' => $clientId, 'user_id' => $user->id, 'status' => 'active', 'cost_usd' => null,
    ]);
});

it('reports the gemini provider + endpoint when the gemini driver is bound', function () {
    // Second minter on a fake: the Gemini driver path returns provider/endpoint for that vendor.
    $this->app->instance(
        RealtimeTokenPort::class,
        new FakeRealtimeTokenMinter($this->app->make(Clock::class), 'gemini', 'wss://gemini.fake/live'),
    );
    [$user, $token] = premiumLearner();
    $collectionId = seedPracticeCollection($user);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/practice/dialogs', ['collection_id' => $collectionId, 'client_id' => Ulid::generate()])
        ->assertCreated()
        ->assertJsonPath('provider', 'gemini')
        ->assertJsonPath('endpoint', 'wss://gemini.fake/live');
});

it('returns a pre-rendered gemini session_setup carrying the lesson CEFR rules', function () {
    // Real Gemini minter, HTTP faked: it renders the setup itself; only the network call is stubbed.
    Http::fake(['*generativelanguage.googleapis.com*' => Http::response(['name' => 'auth_tokens/fake-abc'])]);
    config(['services.practice.driver' => 'gemini']); // → the lesson carries the Gemini model
    $this->app->bind(RealtimeTokenPort::class, fn ($app) => new GeminiLiveTokenMinter(
        apiKey: 'test-key',
        instructions: $app->make(PracticeDialogInstructions::class),
        clock: $app->make(Clock::class),
        promptVersion: 'v3',
        constrained: false,
    ));

    [$user, $token] = premiumLearner('A2'); // A2 → strict speech rules in the system instruction
    $collectionId = seedPracticeCollection($user);

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/practice/dialogs', ['collection_id' => $collectionId, 'client_id' => Ulid::generate()])
        ->assertCreated()
        ->assertJsonPath('provider', 'gemini')
        ->assertJsonPath('session_setup.model', 'models/gemini-3.1-flash-live-preview');

    // The client renders nothing: the system instruction is pre-rendered with THIS lesson's A2 rules.
    $systemText = $response->json('session_setup.systemInstruction.parts.0.text');
    expect($systemText)
        ->toContain('~8 words')
        ->toContain('Do NOT use contractions');

    // Ephemeral tokens are only accepted on the v1alpha ...Constrained WS service.
    expect($response->json('endpoint'))
        ->toContain('v1alpha')
        ->toContain('BidiGenerateContentConstrained');
});

it('refuses a non-premium user with 403 subscription_required', function () {
    $user = User::factory()->create(); // no premium profile
    $token = $user->createToken('device')->plainTextToken;
    $collectionId = seedPracticeCollection($user);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/practice/dialogs', ['collection_id' => $collectionId, 'client_id' => Ulid::generate()])
        ->assertStatus(403)
        ->assertJsonPath('code', 'subscription_required');

    expect(DB::table('practice_dialogs')->count())->toBe(0); // nothing minted or stored
});

it('enforces the daily limit with 429 + resets_at', function () {
    $this->app->instance(PracticeDailyLimit::class, new PracticeDailyLimit(1));
    [$user, $token] = premiumLearner();
    $collectionId = seedPracticeCollection($user);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/practice/dialogs', ['collection_id' => $collectionId, 'client_id' => Ulid::generate()])
        ->assertCreated();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/practice/dialogs', ['collection_id' => $collectionId, 'client_id' => Ulid::generate()])
        ->assertStatus(429)
        ->assertJsonPath('code', 'practice_dialogs_quota_exceeded')
        ->assertJsonPath('meta.limit', 1)
        ->assertJsonPath('meta.resets_at', '2026-08-08T00:00:00+00:00');
});

it('ingests transcripts idempotently and scores target-word coverage', function () {
    [$user, $token] = premiumLearner();
    $collectionId = seedPracticeCollection($user);
    $clientId = Ulid::generate();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/practice/dialogs', ['collection_id' => $collectionId, 'client_id' => $clientId])
        ->assertCreated();

    $events = ['events' => [
        ['role' => 'assistant', 'text' => 'Welcome. How may I help?', 'ts' => 1],
        ['role' => 'user', 'text' => 'I would like to withdraw cash, please.', 'ts' => 2],
    ]];

    $first = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/practice/dialogs/{$clientId}/transcripts", $events)
        ->assertOk()
        ->json('target_words');

    $used = collect($first)->firstWhere('text', 'withdraw cash');
    expect($used['used'])->toBeTrue()
        ->and(collect($first)->firstWhere('text', 'exchange rate')['used'])->toBeFalse();

    // Re-uploading the same batch stores nothing new (idempotent by (role, ts)).
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/practice/dialogs/{$clientId}/transcripts", $events)
        ->assertOk();

    expect(DB::table('practice_dialog_messages')->where('dialog_id', $clientId)->count())->toBe(2);
});

it('finishes with a native-language recap, word counts and a recorded cost', function () {
    [$user, $token] = premiumLearner();
    $collectionId = seedPracticeCollection($user);
    $clientId = Ulid::generate();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/practice/dialogs', ['collection_id' => $collectionId, 'client_id' => $clientId])
        ->assertCreated();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/practice/dialogs/{$clientId}/transcripts", ['events' => [
            ['role' => 'user', 'text' => 'I want to withdraw cash and check my account balance.', 'ts' => 1],
        ]])->assertOk();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/practice/dialogs/{$clientId}/finish")
        ->assertOk()
        ->assertJsonPath('words_total', 3)
        ->assertJsonPath('words_used', 2)
        ->assertJsonStructure(['summary', 'words_used', 'words_total']);

    // The dialog is closed and its (estimated) spend is recorded.
    $row = DB::table('practice_dialogs')->where('id', $clientId)->first();
    expect($row->status)->toBe('finished')
        ->and($row->cost_usd)->not->toBeNull();
});

it('never writes reviews or (user, term) progress — it is practice', function () {
    [$user, $token] = premiumLearner();
    $collectionId = seedPracticeCollection($user);
    $clientId = Ulid::generate();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/practice/dialogs', ['collection_id' => $collectionId, 'client_id' => $clientId])
        ->assertCreated();
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/practice/dialogs/{$clientId}/transcripts", ['events' => [
            ['role' => 'user', 'text' => 'withdraw cash', 'ts' => 1],
        ]])->assertOk();
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/practice/dialogs/{$clientId}/finish")->assertOk();

    expect(DB::table('user_term_progress')->where('user_id', $user->id)->count())->toBe(0)
        ->and(DB::table('reviews')->count())->toBe(0);
});

it('expires a stale dialog via the background sweep, recording its estimated cost', function () {
    [$user, $token] = premiumLearner();
    $collectionId = seedPracticeCollection($user);
    $clientId = Ulid::generate();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/practice/dialogs', ['collection_id' => $collectionId, 'client_id' => $clientId])
        ->assertCreated();

    // Jump past the token TTL (start now + 200s) and sweep.
    $this->app->instance(Clock::class, new FixedClock(new DateTimeImmutable('2026-08-07T10:10:00+00:00')));
    $this->artisan('practice:expire-dialogs')->assertOk();

    $row = DB::table('practice_dialogs')->where('id', $clientId)->first();
    expect($row->status)->toBe('expired')
        ->and($row->cost_usd)->not->toBeNull();
});

it('serves the last concluded dialog result for a collection', function () {
    [$user, $token] = premiumLearner();
    $collectionId = seedPracticeCollection($user);
    $clientId = Ulid::generate();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/practice/dialogs', ['collection_id' => $collectionId, 'client_id' => $clientId])
        ->assertCreated();
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/practice/dialogs/{$clientId}/transcripts", ['events' => [
            ['role' => 'user', 'text' => 'I want to withdraw cash and check my account balance.', 'ts' => 1],
        ]])->assertOk();
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/practice/dialogs/{$clientId}/finish")->assertOk();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/v1/practice/collections/{$collectionId}/last-dialog")
        ->assertOk()
        ->assertJsonPath('words_total', 3)
        ->assertJsonPath('words_used', 2)
        ->assertJsonStructure(['finished_at', 'words_used', 'words_total', 'summary']);
});

it('404s on last-dialog when the collection never had one', function () {
    [, $token] = premiumLearner();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/practice/collections/' . Ulid::generate() . '/last-dialog')
        ->assertStatus(404);
});

it('404s on finish for an unknown dialog', function () {
    [, $token] = premiumLearner();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/practice/dialogs/' . Ulid::generate() . '/finish')
        ->assertStatus(404)
        ->assertJsonPath('code', 'practice_dialog_not_found');
});

it('requires authentication', function () {
    $this->postJson('/api/v1/practice/dialogs', ['collection_id' => Ulid::generate(), 'client_id' => Ulid::generate()])
        ->assertUnauthorized();
});

// ── F25: realtime dialog on a SUBSCRIBED store collection (owner NULL) ──────────────────

it('starts a realtime dialog on a subscribed store collection (F25)', function () {
    [$user, $token] = premiumLearner();
    $cid = subscribedStoreDeck($user, active: true);
    $clientId = Ulid::generate();

    // Before F25 the lesson loaded the collection owner-only → null → 404 for a store deck.
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/practice/dialogs', ['collection_id' => $cid, 'client_id' => $clientId])
        ->assertCreated()
        ->assertJsonPath('dialog_id', $clientId);
});

it('404s a realtime dialog on a store collection after unsubscribe (F25)', function () {
    [$user, $token] = premiumLearner();
    $cid = subscribedStoreDeck($user, active: false); // tombstoned subscription

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/practice/dialogs', ['collection_id' => $cid, 'client_id' => Ulid::generate()])
        ->assertNotFound();
});
