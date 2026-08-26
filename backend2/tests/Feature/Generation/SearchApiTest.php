<?php

declare(strict_types=1);

use App\Modules\Generation\Application\Port\WordLookupPort;
use App\Modules\Generation\Domain\Service\SearchLookupDailyLimit;
use App\Modules\Generation\Infrastructure\Adapter\FakeWordLookup;
use App\Modules\Identity\Infrastructure\Eloquent\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    // No network in tests: the deterministic lookup, whose answer deliberately passes the barrier.
    $this->app->bind(WordLookupPort::class, FakeWordLookup::class);
});

it('finds an existing term by its own text, for free', function () {
    [$user, $token] = learner();
    [, $termId] = seedCollectionWith($user, 'invoice', 'счёт');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/search?q=invoice')
        ->assertOk()
        ->assertJsonPath('data.0.term_id', $termId)
        ->assertJsonPath('data.0.text', 'invoice');

    // Free means free: nothing was bought.
    expect(DB::table('search_lookups')->count())->toBe(0);
});

it('finds a term by its translation and by a prefix', function () {
    [$user, $token] = learner();
    seedCollectionWith($user, 'invoice', 'счёт');

    $byTranslation = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/search?q=счёт')->assertOk()->json('data');
    $byPrefix = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/search?q=invo')->assertOk()->json('data');

    expect($byTranslation)->toHaveCount(1)->and($byPrefix)->toHaveCount(1);
});

it('finds a word by the RUSSIAN the learner was reaching for', function () {
    [$user, $token] = learner();
    [, $termId] = seedCollectionWith($user, 'occasion', 'случай');

    // The free half of search already works both ways round — a learner who cannot name the word in
    // English types what they can, and the word they meant comes back. Pinned because the reverse
    // direction is now the feature and not a side effect of matching translations.
    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/search?q=' . urlencode('Случай'))
        ->assertOk()
        ->assertJsonPath('data.0.term_id', $termId)
        ->assertJsonPath('data.0.text', 'occasion')
        ->assertJsonPath('data.0.translation', 'случай');

    expect(DB::table('search_lookups')->count())->toBe(0);
});

it('says which of the user\'s own folders already hold a hit', function () {
    [$user, $token] = learner();
    [$collectionId, ] = seedCollectionWith($user, 'ledger', 'книга учёта');

    $hit = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/search?q=ledger')->assertOk()->json('data.0');

    expect($hit['folders'])->toHaveCount(1)
        ->and($hit['folders'][0]['id'])->toBe($collectionId);
});

it("does not leak another user's folders into the membership flag", function () {
    $owner = User::factory()->create();
    seedCollectionWith($owner, 'ledger', 'книга учёта');

    [, $token] = learner();
    $hit = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/search?q=ledger')->assertOk()->json('data.0');

    expect($hit['folders'])->toBe([]);
});

it('looks a new word up once and serves every later ask from the cache', function () {
    [, $token] = learner();
    [, $otherToken] = learner();

    $first = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/search/lookup', ['query' => 'Reimbursement'])
        ->assertOk()
        ->assertJsonPath('data.limit_reached', false)
        ->assertJsonPath('data.lookup.text', 'reimbursement')
        ->assertJsonPath('data.lookup.fresh', true)
        ->json('data.lookup.lookup_id');

    // A DIFFERENT user, same word: the answer is a fact about the word, so it costs nobody anything.
    $second = $this->withHeader('Authorization', "Bearer {$otherToken}")
        ->postJson('/api/v1/search/lookup', ['query' => '  reimbursement '])
        ->assertOk()
        ->assertJsonPath('data.lookup.fresh', false)
        ->json('data.lookup.lookup_id');

    expect($second)->toBe($first);
    expect(DB::table('search_lookups')->count())->toBe(1);
    // The lookup created no term: a word is only written when the learner says «save this».
    expect(DB::table('terms')->where('text', 'reimbursement')->count())->toBe(0);
});

it('answers honestly when the daily cap is spent, and still costs nothing', function () {
    config()->set('services.generation.search_lookup_daily_cap', 2);
    $this->app->bind(SearchLookupDailyLimit::class, fn () => new SearchLookupDailyLimit(2));

    [, $token] = learner();
    foreach (['alpha', 'bravo'] as $word) {
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/search/lookup', ['query' => $word])
            ->assertOk()->assertJsonPath('data.limit_reached', false);
    }

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/search/lookup', ['query' => 'charlie'])
        ->assertOk()
        ->assertJsonPath('data.limit_reached', true)
        ->assertJsonPath('data.lookup', null)
        ->assertJsonPath('data.daily_cap', 2);

    expect(DB::table('search_lookups')->count())->toBe(2);
});

it('serves a cached word even when the cap is spent — nothing is bought', function () {
    [, $token] = learner();
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/search/lookup', ['query' => 'alpha'])->assertOk();

    // Cap drops to zero AFTER the word is in the cache.
    $this->app->bind(SearchLookupDailyLimit::class, fn () => new SearchLookupDailyLimit(0));

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/search/lookup', ['query' => 'alpha'])
        ->assertOk()
        ->assertJsonPath('data.limit_reached', false)
        ->assertJsonPath('data.lookup.text', 'alpha');
});

it('re-asks for a word cached before the illustration question existed, and replaces the row', function () {
    [, $token] = learner();

    // A v1 row: a perfectly good card, written before the prompt asked whether the word can be
    // illustrated. Its `image_api_prompt` key is ABSENT — which is not the same as an empty one.
    DB::table('search_lookups')->insert([
        'id' => \App\Modules\Shared\Domain\ValueObject\Ulid::generate(),
        'user_id' => null,
        'normalized_query' => 'reimbursement',
        'lang' => 'en',
        'native_lang' => 'ru',
        'payload' => json_encode([
            'text' => 'reimbursement', 'type' => 'word', 'translation' => 'возмещение',
            'description' => 'Money you get back.', 'example' => null,
            'example_translation' => null, 'cefr' => 'B2', 'transcription' => null,
        ], JSON_UNESCAPED_UNICODE),
        'model' => 'gpt-4o-mini',
        'prompt_version' => 'lookup.v1',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $card = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/search/lookup', ['query' => 'reimbursement'])
        ->assertOk()
        ->assertJsonPath('data.lookup.fresh', true)   // paid for, because the old row could not answer
        ->json('data.lookup');

    // ONE row still — the refresh REPLACED it rather than piling a second answer beside it, so the
    // cache key keeps meaning «the answer for this word».
    expect(DB::table('search_lookups')->where('normalized_query', 'reimbursement')->count())->toBe(1);
    expect(DB::table('search_lookups')->where('normalized_query', 'reimbursement')->value('prompt_version'))
        ->not->toBe('lookup.v1');
    expect($card['text'])->toBe('reimbursement');
});

it('serves a stale cached row rather than nothing when the cap is spent', function () {
    $this->app->bind(SearchLookupDailyLimit::class, fn () => new SearchLookupDailyLimit(0));
    [, $token] = learner();

    DB::table('search_lookups')->insert([
        'id' => \App\Modules\Shared\Domain\ValueObject\Ulid::generate(),
        'user_id' => null,
        'normalized_query' => 'reimbursement',
        'lang' => 'en',
        'native_lang' => 'ru',
        'payload' => json_encode(['text' => 'reimbursement', 'type' => 'word',
            'translation' => 'возмещение', 'description' => 'Money you get back.'], JSON_UNESCAPED_UNICODE),
        'model' => 'gpt-4o-mini',
        'prompt_version' => 'lookup.v1',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // A missing photo is not worth withholding a readable card over.
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/search/lookup', ['query' => 'reimbursement'])
        ->assertOk()
        ->assertJsonPath('data.limit_reached', false)
        ->assertJsonPath('data.lookup.text', 'reimbursement');
});

it('gives a saved word an image query, so its photo can be fetched', function () {
    [, $token] = learner();
    $lookupId = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/search/lookup', ['query' => 'reimbursement'])
        ->json('data.lookup.lookup_id');

    $termId = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/search/add', ['lookup_id' => $lookupId])
        ->assertCreated()->json('data.term_id');

    // The query is what makes the term PENDING for a photo; without it the image job would skip it
    // forever and the card would keep its placeholder. That was the live defect this pins.
    expect(DB::table('terms')->where('id', $termId)->value('image_api_prompt'))->not->toBeEmpty();
});

it('saves a looked-up word into «Сохранённые» and enrols it', function () {
    [$user, $token] = learner();
    $lookupId = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/search/lookup', ['query' => 'reimbursement'])
        ->json('data.lookup.lookup_id');

    $saved = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/search/add', ['lookup_id' => $lookupId])
        ->assertCreated()
        ->assertJsonPath('data.collection_is_default', true)
        ->assertJsonPath('data.added', true)
        ->assertJsonPath('data.enrolled', true)
        ->json('data');

    $this->assertDatabaseHas('terms', ['id' => $saved['term_id'], 'text' => 'reimbursement']);
    // The description is stored as content, in the language being learned.
    $this->assertDatabaseHas('term_descriptions', ['term_id' => $saved['term_id'], 'lang' => 'en']);
    // In the folder…
    expect(DB::table('collection_items')
        ->where('collection_id', $saved['collection_id'])->where('term_id', $saved['term_id'])
        ->whereNull('deleted_at')->count())->toBe(1);
    // …and in the pool: saving a word you went looking for IS the deliberate act.
    expect(DB::table('user_term_progress')
        ->where('user_id', $user->id)->where('term_id', $saved['term_id'])
        ->whereNotNull('enrolled_at')->count())->toBe(1);
});

it('is idempotent: saving the same word twice makes one term and one item', function () {
    [, $token] = learner();
    $lookupId = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/search/lookup', ['query' => 'reimbursement'])
        ->json('data.lookup.lookup_id');

    $save = fn () => $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/search/add', ['lookup_id' => $lookupId])->assertCreated();

    $first = $save()->json('data');
    $second = $save()->json('data');

    expect($second['term_id'])->toBe($first['term_id'])
        ->and($second['added'])->toBeFalse()   // already there — the tap was a replay
        ->and($second['enrolled'])->toBeFalse();
    expect(DB::table('terms')->where('text', 'reimbursement')->count())->toBe(1);
    expect(DB::table('collection_items')->where('term_id', $first['term_id'])->count())->toBe(1);
});

it('saves an existing term into a named folder', function () {
    [$user, $token] = learner();
    [, $termId] = seedCollectionWith($user, 'invoice', 'счёт', enroll: false);
    $folder = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/collections', ['title' => 'Банк'])->json('data.id');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/search/add', ['term_id' => $termId, 'collection_id' => $folder])
        ->assertCreated()
        ->assertJsonPath('data.collection_id', $folder)
        ->assertJsonPath('data.collection_title', 'Банк')
        ->assertJsonPath('data.collection_is_default', false)
        ->assertJsonPath('data.enrolled', true);
});

it("refuses to save into another user's folder, and enrols nothing", function () {
    [$user, $token] = learner();
    [, $termId] = seedCollectionWith($user, 'invoice', 'счёт', enroll: false);
    $foreign = app(\App\Modules\Collections\Application\Command\CreateCustomCollectionHandler::class)(
        new \App\Modules\Collections\Application\Command\CreateCustomCollection(
            \App\Modules\Shared\Domain\ValueObject\UserId::fromString(User::factory()->create()->id),
            'Чужая',
            new \App\Modules\Shared\Domain\ValueObject\LanguageCode('ru'),
            new \App\Modules\Shared\Domain\ValueObject\LanguageCode('en'),
        ),
    )->value;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/search/add', ['term_id' => $termId, 'collection_id' => $foreign])
        ->assertStatus(403)
        ->assertJsonPath('code', 'collection_not_editable');

    expect(DB::table('user_term_progress')
        ->where('user_id', $user->id)->where('term_id', $termId)
        ->whereNotNull('enrolled_at')->count())->toBe(0);
});

it('refuses a save that names both a lookup and a term', function () {
    [, $token] = learner();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/search/add', [
            'lookup_id' => \App\Modules\Shared\Domain\ValueObject\Ulid::generate(),
            'term_id' => \App\Modules\Shared\Domain\ValueObject\Ulid::generate(),
        ])
        ->assertStatus(422);
});

it('404s a save pointing at a lookup that is not in the cache', function () {
    [, $token] = learner();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/search/add', ['lookup_id' => \App\Modules\Shared\Domain\ValueObject\Ulid::generate()])
        ->assertStatus(404)
        ->assertJsonPath('code', 'lookup_not_found');
});

it('shows a saved word\'s description on the next free search', function () {
    [, $token] = learner();
    $lookupId = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/search/lookup', ['query' => 'reimbursement'])
        ->json('data.lookup.lookup_id');
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/search/add', ['lookup_id' => $lookupId])->assertCreated();

    $hit = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/search?q=reimbursement')->assertOk()->json('data.0');

    expect($hit['description'])->not->toBeNull()
        ->and($hit['folders'])->toHaveCount(1)
        ->and($hit['folders'][0]['is_default'])->toBeTrue();
});

/**
 * LKP-1 — the OTHER READINGS, and the switch this door never asked.
 *
 * `GENERATION_WRITE_OTHER_TRANSLATIONS` was born beside the станок's core and bound only there, so
 * from the day the save button existed until now every looked-up word wrote its alternative readings
 * into `term_translations` unconditionally — the same shape of hole the synonym flag had, found the
 * same way. SYN-1e measured the product at 29% clean, which is why the switch is off.
 *
 * The PRIMARY is not part of this and never was (SYN-1a): it is the reading the learner read and
 * confirmed, and it is written at every flag value. That is what these tests are mostly about.
 */
describe('the other readings, on the same switch as the станок', function (): void {
    /** @return array{0: string, 1: string} term id, the token that saved it */
    $saveLookedUpWord = function (object $ctx, ?string $confirmed = null): array {
        [, $token] = learner();
        $lookupId = $ctx->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/search/lookup', ['query' => 'reimbursement'])
            ->json('data.lookup.lookup_id');

        $termId = $ctx->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/search/add', array_filter([
                'lookup_id' => $lookupId,
                'fixed_translation' => $confirmed,
            ], static fn (?string $v): bool => $v !== null))
            ->assertCreated()->json('data.term_id');

        return [$termId, $token];
    };

    it('writes ONLY the primary while the switch is off — today\'s value', function () use ($saveLookedUpWord) {
        config(['services.generation.write_other_translations' => false]);

        [$termId] = $saveLookedUpWord($this);

        $rows = DB::table('term_translations')->where('term_id', $termId)->get(['text', 'is_primary']);

        // One row, and it is the pinned one. The fake's «другой перевод» is the alternative this
        // door used to write regardless; its absence is the whole fix.
        expect($rows)->toHaveCount(1)
            ->and($rows[0]->is_primary)->toBeTrue()
            ->and($rows[0]->text)->toBe('перевод');
    });

    it('writes them beside the primary while the switch is on', function () use ($saveLookedUpWord) {
        config(['services.generation.write_other_translations' => true]);

        [$termId] = $saveLookedUpWord($this);

        $rows = DB::table('term_translations')->where('term_id', $termId)
            ->orderByDesc('is_primary')->get(['text', 'is_primary']);

        expect($rows)->toHaveCount(2)
            ->and($rows[0]->is_primary)->toBeTrue()
            ->and($rows[0]->text)->toBe('перевод')
            // Additive and never primary — the gate changes whether they are written, not what a
            // card asks.
            ->and($rows[1]->is_primary)->toBeFalse()
            ->and($rows[1]->text)->toBe('другой перевод');
    });

    it('pins the CONFIRMED reading at either flag value — the primary is nobody\'s switch', function () use ($saveLookedUpWord) {
        // The case the gate must not touch: the learner edited the reading before saving. With the
        // switch off the model's own reading is not kept beside it any more — and the line the
        // learner typed is still exactly what the card carries.
        config(['services.generation.write_other_translations' => false]);
        [$offTermId] = $saveLookedUpWord($this, 'компенсация');

        config(['services.generation.write_other_translations' => true]);
        [$onTermId] = $saveLookedUpWord($this, 'компенсация');

        $primaryOf = fn (string $id): ?string => DB::table('term_translations')
            ->where('term_id', $id)->where('is_primary', true)->value('text');

        expect($primaryOf($offTermId))->toBe('компенсация')
            ->and($primaryOf($onTermId))->toBe('компенсация')
            // Same term twice (terms dedup on their text), so «either value» is literal here.
            ->and($onTermId)->toBe($offTermId);
    });

    it('writes no translation at all on the term_id branch, at either flag value', function () {
        // The second door of the same endpoint: «save a word that already exists into this folder».
        // It carries no lookup and no reading, so it has never written a translation row — pinned
        // here so the branch cannot grow one quietly.
        foreach ([false, true] as $flag) {
            config(['services.generation.write_other_translations' => $flag]);

            [$user, $token] = learner();
            [, $termId] = seedCollectionWith($user, 'invoice', 'счёт', enroll: false);
            $before = DB::table('term_translations')->where('term_id', $termId)->count();

            $folder = $this->withHeader('Authorization', "Bearer {$token}")
                ->postJson('/api/v1/collections', ['title' => 'Банк'])->json('data.id');

            $this->withHeader('Authorization', "Bearer {$token}")
                ->postJson('/api/v1/search/add', ['term_id' => $termId, 'collection_id' => $folder])
                ->assertCreated();

            expect(DB::table('term_translations')->where('term_id', $termId)->count())->toBe($before);
        }
    });
});
