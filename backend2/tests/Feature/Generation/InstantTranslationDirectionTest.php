<?php

declare(strict_types=1);

use App\Modules\Generation\Application\Dto\InstantTranslation;
use App\Modules\Generation\Application\Port\TranslationProvider;
use App\Modules\Generation\Domain\Service\SearchQueryLength;
use App\Modules\Generation\Infrastructure\Adapter\DeepLTranslator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * The search field takes BOTH halves of the pair, and the LEARNER says which way.
 *
 * These tests pin the decision that replaced an earlier design: the vendor's own language detection
 * does not get a vote. On a single word it is confidently wrong often enough to matter — «gate»
 * reads as Norwegian and comes back «улица», «случай» as Bulgarian — and there is no repair for
 * that, because a wrong answer looks exactly like a right one. So the direction comes from the pill
 * and the provider is told both languages, every time.
 */
it('answers a native-language query with the word being taught', function () {
    $fake = fakeTranslator();
    [, $token] = learner();

    $hint = instant($this, $token, 'случай', 'ru', 'en');

    expect($hint['translation'])->toBe('occasion')
        // Internal: it tells the screen which of the two strings is the headline. The screen never
        // says a word about languages or direction — it just answers.
        ->and($hint['reversed'])->toBeTrue();
    // BOTH sides named. Never «auto», which is the whole point.
    expect($fake->directions)->toBe(['ru→en']);
});

it('answers the other way when the pill is the other way', function () {
    $fake = fakeTranslator();
    [, $token] = learner();

    $hint = instant($this, $token, 'occasion', 'en', 'ru');

    expect($hint['translation'])->toBe('перевод: occasion')
        ->and($hint['reversed'])->toBeFalse();
    expect($fake->directions)->toBe(['en→ru']);
});

it('honours a stated direction even when the query is in the other language', function () {
    // The learner left the pill on EN → RU and typed Russian. We translate what they asked for
    // rather than second-guessing them: a direction they can see and flip in one tap is a mistake
    // that fixes itself, and a silent override is the failure this design exists to avoid.
    $fake = fakeTranslator();
    [, $token] = learner();

    instant($this, $token, 'случай', 'en', 'ru');

    expect($fake->directions)->toBe(['en→ru']);
});

it('falls back to the learner\'s own pair when no pill is sent', function () {
    $fake = fakeTranslator();
    [, $token] = learner();

    $hint = instant($this, $token, 'occasion');

    // Taught language into their own — where the pill starts, and what a caller with no pill means.
    expect($fake->directions)->toBe(['en→ru'])->and($hint['reversed'])->toBeFalse();
});

it('refuses a pair with no taught language in it at all', function () {
    fakeTranslator();
    [, $token] = learner();

    // A pair is «taught ↔ read», and neither Russian nor Ukrainian is a language this product
    // teaches. The pill only ever offers what `GET /search/languages` returned, so this is a stale
    // client or a hand-made request. Answering it in a DIFFERENT pair would make the label on the
    // screen a lie, on the one screen whose whole job is to be believed.
    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/search/instant?q=word&source=ru&target=uk')
        ->assertStatus(422)
        ->assertJsonPath('code', 'unsupported_language_pair');
});

it('refuses a language into itself', function () {
    fakeTranslator();
    [, $token] = learner();

    // The shape a swapped-twice client bug takes: it would ask the vendor to translate a word into
    // its own language.
    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/search/instant?q=word&source=en&target=en')
        ->assertStatus(422)
        ->assertJsonPath('code', 'unsupported_language_pair');
});

it('serves a pair with no English in it', function () {
    // Was a 422 until RS-3: the taught side had to be `en` and the support side had to be one of
    // two languages listed in an env var. Neither is true any more — the taught side is any
    // language the capability table can teach, the support side any language the catalogue names
    // (DECISIONS пп. 85, 134).
    $fake = fakeTranslator();
    [, $token] = learner();

    $hint = instant($this, $token, 'factură', 'ro', 'uk');

    expect($hint['translation'])->not->toBeNull();
    expect($fake->directions)->toBe(['ro→uk']);
});

it('serves the second language of the pair when the pill is set to it', function () {
    $fake = fakeTranslator();
    [, $token] = learner();

    $hint = instant($this, $token, 'occasion', 'en', 'ro');

    expect($hint['translation'])->not->toBeNull()->and($hint['reversed'])->toBeFalse();
    expect($fake->directions)->toBe(['en→ro']);
    expect((string) DB::table('instant_translations')->value('lang_pair'))->toBe('en:ro');
});

it('caches each direction under its own key, so one never answers for the other', function () {
    fakeTranslator();
    [, $token] = learner();

    instant($this, $token, 'случай', 'ru', 'en');
    instant($this, $token, 'occasion', 'en', 'ru');

    $pairs = DB::table('instant_translations')->orderBy('lang_pair')->pluck('lang_pair')->all();
    expect($pairs)->toBe(['en:ru', 'ru:en']);
});

it('serves a reversed word from the cache without touching the vendor again', function () {
    $fake = fakeTranslator();
    [, $token] = learner();
    [, $otherToken] = learner();

    $first = instant($this, $token, 'случай', 'ru', 'en');
    $second = instant($this, $otherToken, '  Случай ', 'ru', 'en');

    expect($second['translation'])->toBe($first['translation'])
        ->and($second['source'])->toBe('cache')
        ->and($second['reversed'])->toBeTrue();
    expect($fake->calls)->toBe(1, 'a cache hit must never reach the vendor');
});

it('answers a native-language query from OUR OWN catalogue, free, before any vendor', function () {
    $fake = fakeTranslator();
    [$user, $token] = learner();
    seedCollectionWith($user, 'invoice', 'счёт');

    $hint = instant($this, $token, 'Счёт', 'ru', 'en');

    // The term's own text, not a machine translation of the word for it: this is what the card
    // says, and a hint that disagreed with its own card would be worse than none.
    expect($hint['translation'])->toBe('invoice')
        ->and($hint['source'])->toBe('vocabulary')
        ->and($hint['reversed'])->toBeTrue();
    expect($fake->calls)->toBe(0);
    expect(DB::table('instant_translations')->count())->toBe(0);
});

it('never serves a cached row that says a word means itself', function () {
    // Rows like this are real: for a while this code let the vendor detect the source, so «случай»
    // asked for in Russian came straight back and was cached. The cache is permanent, so without a
    // guard they would answer «случай — случай» forever on the one screen whose job is to name a
    // word the learner cannot name yet.
    $fake = fakeTranslator();
    [, $token] = learner();
    DB::table('instant_translations')->insert([
        'id' => \App\Modules\Shared\Domain\ValueObject\Ulid::generate(),
        'normalized_text' => 'случай',
        'lang_pair' => 'ru:en',
        'translation' => 'случай',
        'provider' => DeepLTranslator::NAME,
        'characters' => 6,
        'created_at' => now(),
    ]);

    $hint = instant($this, $token, 'случай', 'ru', 'en');

    // Skipped, bought properly, stored under the same key — self-healing, nothing deleted.
    expect($hint['translation'])->toBe('occasion')->and($hint['source'])->toBe(DeepLTranslator::NAME);
    expect($fake->calls)->toBe(1);
});

it('does not teach the cache a non-answer when the vendor echoes the query back', function () {
    app()->instance(TranslationProvider::class, new class implements TranslationProvider
    {
        public function isAvailable(): bool
        {
            return true;
        }

        public function name(): string
        {
            return DeepLTranslator::NAME;
        }

        public function translate(string $text, string $source, string $target): ?InstantTranslation
        {
            return new InstantTranslation($text, DeepLTranslator::NAME, mb_strlen($text));
        }
    });
    [, $token] = learner();

    expect(instant($this, $token, 'occasion', 'en', 'ru')['translation'])->toBeNull();
    expect(DB::table('instant_translations')->count())->toBe(0);
});

it('refuses a paragraph before it reaches the vendor', function () {
    $fake = fakeTranslator();
    [, $token] = learner();

    $hint = instant($this, $token, str_repeat('слово ', 40), 'ru', 'en');

    expect($hint['query_too_long'])->toBeTrue()
        ->and($hint['translation'])->toBeNull()
        ->and($hint['feature_disabled'])->toBeFalse()
        ->and($hint['limit_reached'])->toBeFalse();
    // The characters are what the plan is billed in, so this is a bill and not merely a bad answer.
    expect($fake->calls)->toBe(0);
    expect(DB::table('instant_translations')->count())->toBe(0);
});

it('lets a long phrase through, right up to the limit', function () {
    $fake = fakeTranslator();
    [, $token] = learner();
    $max = app(SearchQueryLength::class)->max();

    expect(instant($this, $token, str_repeat('a', $max), 'en', 'ru')['query_too_long'])->toBeFalse();
    expect(instant($this, $token, str_repeat('b', $max + 1), 'en', 'ru')['query_too_long'])->toBeTrue();
    expect($fake->calls)->toBe(1);
});

it('refuses the same paragraph at the PAID lookup, so the two agree', function () {
    [, $token] = learner();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/search/lookup', ['query' => str_repeat('a', 121)])
        ->assertStatus(422);
});

describe('GET /search/languages', function () {
    it('tells the client which pairs the pill may offer', function () {
        [, $token] = learner();

        $data = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/search/languages')->assertOk()->json('data');

        expect($data['targets'])->toContain('en')
            ->and($data['targets'])->toContain('ro')
            ->and($data['targets'])->toContain('pl')
            // Reference-only languages carry no trainers, so they are not taught (пп. 84, 136) and
            // the search does not offer them as the term side yet.
            ->and($data['targets'])->not->toContain('zh')
            ->and($data['targets'])->not->toContain('ja')
            // Reading takes a name and nothing else, so the support side is the whole catalogue —
            // including the languages we teach, and including the ones we do not.
            ->and($data['natives'])->toContain('ru')
            ->and($data['natives'])->toContain('ro')
            ->and($data['natives'])->toContain('uk')
            ->and($data['natives'])->toContain('tr')
            // The legacy single-language field, frozen for the app already on the phone.
            ->and($data['target'])->toBe('en')
            // Where the pill starts on a fresh device: the learner's own language, from their
            // profile — not the first entry of the list.
            ->and($data['default_native'])->toBe('ru');
    });

    it('offers nothing the search would then refuse', function () {
        fakeTranslator();
        [, $token] = learner();

        $data = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/search/languages')->assertOk()->json('data');

        // The WHOLE matrix against the rule the endpoints apply — 7 × 13 both ways round is far more
        // than one test may spend HTTP requests on (the rate limiter refuses at ~60), and the lists
        // under test are the ones the response just advertised.
        $rule = app(\App\Modules\Generation\Domain\Service\SupportedLanguages::class);
        foreach ($data['targets'] as $target) {
            foreach ($data['natives'] as $native) {
                if ($target === $native) {
                    continue; // not a pair — the one combination of the two lists that is refused
                }
                expect($rule->supports($target, $native))->toBeTrue("{$target} → {$native}")
                    ->and($rule->supports($native, $target))->toBeTrue("{$native} → {$target}");
            }
        }

        // …and a walk down the wire for a sample of it, so the rule above is the one the HTTP path
        // really runs: the pair the app opens in, a pair with no English, and one both ways round.
        foreach ([['en', 'ru'], ['ro', 'uk'], ['de', 'tr'], ['tr', 'de']] as [$source, $answer]) {
            $this->withHeader('Authorization', "Bearer {$token}")
                ->getJson("/api/v1/search/instant?q=word&source={$source}&target={$answer}")
                ->assertOk();
        }
    });
});
