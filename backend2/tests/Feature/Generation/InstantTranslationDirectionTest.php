<?php

declare(strict_types=1);

use App\Modules\Generation\Application\Dto\InstantTranslation;
use App\Modules\Generation\Application\Port\TranslationProvider;
use App\Modules\Generation\Domain\Service\SearchQueryLength;
use App\Modules\Generation\Infrastructure\Adapter\DeepLTranslator;
use App\Modules\Generation\Infrastructure\Adapter\FakeTranslator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * The search field takes BOTH halves of the learner's pair, and the reverse direction is the one
 * that matters: «случай» → «occasion» is somebody reaching for a word they cannot yet name, which
 * is the whole reason to open this screen.
 *
 * What these tests pin is that the DETECTOR decides and the alphabet does not. The alphabet gets to
 * pick which direction we ask for first — somebody has to go first — and is overruled the moment
 * the provider says otherwise.
 */
it('answers a native-language query with the ENGLISH word', function () {
    $fake = fakeTranslator();
    [, $token] = learner();

    $hint = instant($this, $token, 'случай');

    expect($hint['translation'])->toBe('occasion')
        // Internal: it tells the screen which of the two strings is the headline. The screen never
        // says a word about languages or direction — it just answers.
        ->and($hint['reversed'])->toBeTrue();
    // Asked with the source left to the provider, and asked for English.
    expect($fake->directions)->toBe(['auto→en']);
});

it('still answers an English query in the learner\'s own language', function () {
    $fake = fakeTranslator();
    [, $token] = learner();

    $hint = instant($this, $token, 'occasion');

    expect($hint['translation'])->toBe('перевод: occasion')
        ->and($hint['reversed'])->toBeFalse();
    expect($fake->directions)->toBe(['auto→ru']);
});

it('caches each direction under its own key, so one never answers for the other', function () {
    fakeTranslator();
    [, $token] = learner();

    instant($this, $token, 'случай');
    instant($this, $token, 'occasion');

    $pairs = DB::table('instant_translations')->orderBy('lang_pair')->pluck('lang_pair')->all();
    expect($pairs)->toBe(['en:ru', 'ru:en']);
});

it('serves a reversed word from the cache without touching the vendor again', function () {
    $fake = fakeTranslator();
    [, $token] = learner();
    [, $otherToken] = learner();

    $first = instant($this, $token, 'случай');
    $second = instant($this, $otherToken, '  Случай ');

    expect($second['translation'])->toBe($first['translation'])
        ->and($second['source'])->toBe('cache')
        ->and($second['reversed'])->toBeTrue();
    expect($fake->calls)->toBe(1, 'a cache hit must never reach the vendor');
});

it('answers a native-language query from OUR OWN catalogue, free, before any vendor', function () {
    $fake = fakeTranslator();
    [$user, $token] = learner();
    seedCollectionWith($user, 'invoice', 'счёт');

    $hint = instant($this, $token, 'Счёт');

    // The term's own text, not a machine translation of the word for it: this is what the card
    // says, and a hint that disagreed with its own card would be worse than none.
    expect($hint['translation'])->toBe('invoice')
        ->and($hint['source'])->toBe('vocabulary')
        ->and($hint['reversed'])->toBeTrue();
    expect($fake->calls)->toBe(0);
    expect(DB::table('instant_translations')->count())->toBe(0);
});

it('lets the DETECTOR overrule the alphabet, and buys the answer again the right way round', function () {
    // A native language written in the Latin alphabet — the case the alphabet heuristic cannot see.
    // The provider says «this is the learner's own language», so the answer must come back in the
    // language being learned even though the script suggested the opposite.
    $provider = new class implements TranslationProvider
    {
        /** @var list<string> */
        public array $targets = [];

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
            $this->targets[] = $target;

            return new InstantTranslation(
                text: $target === 'en' ? 'occasion' : 'ocazie',
                provider: DeepLTranslator::NAME,
                characters: mb_strlen($text),
                // Whatever we asked for, the input was the learner's own language.
                detectedSource: 'ru',
            );
        }
    };
    app()->instance(TranslationProvider::class, $provider);
    [, $token] = learner();

    $hint = instant($this, $token, 'ocazie');

    // The alphabet said «Latin, so they typed English, answer in Russian» and was wrong; the
    // detector's verdict is the one that reaches the learner.
    expect($provider->targets)->toBe(['ru', 'en']);
    expect($hint['translation'])->toBe('occasion')->and($hint['reversed'])->toBeTrue();

    $row = DB::table('instant_translations')->first();
    expect((string) $row->lang_pair)->toBe('ru:en')
        // BOTH calls were billed by the vendor, so both are on the meter — a counter that forgot
        // the wasted one would drift from the real quota.
        ->and((int) $row->characters)->toBe(mb_strlen('ocazie') * 2);
});

it('treats a third language as the language being learned, and answers in the learner\'s own', function () {
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
            return new InstantTranslation(
                text: 'случай',
                provider: DeepLTranslator::NAME,
                characters: mb_strlen($text),
                detectedSource: 'ro',
            );
        }
    });
    [, $token] = learner();

    $hint = instant($this, $token, 'ocazie');

    // Somebody who typed something we cannot place still gets told what it means, in the language
    // they read. That is the useful failure.
    expect($hint['translation'])->toBe('случай')->and($hint['reversed'])->toBeFalse();
    expect((string) DB::table('instant_translations')->value('lang_pair'))->toBe('en:ru');
});

it('refuses a paragraph before it reaches the vendor', function () {
    $fake = fakeTranslator();
    [, $token] = learner();

    $hint = instant($this, $token, str_repeat('слово ', 40));

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

    expect(instant($this, $token, str_repeat('a', $max))['query_too_long'])->toBeFalse();
    expect(instant($this, $token, str_repeat('b', $max + 1))['query_too_long'])->toBeTrue();
    expect($fake->calls)->toBe(1);
});

it('refuses the same paragraph at the PAID lookup, so the two agree', function () {
    [, $token] = learner();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/search/lookup', ['query' => str_repeat('a', 121)])
        ->assertStatus(422);
});
