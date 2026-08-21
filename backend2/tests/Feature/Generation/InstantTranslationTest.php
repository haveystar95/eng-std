<?php

declare(strict_types=1);

use App\Modules\Generation\Application\Port\TranslationProvider;
use App\Modules\Generation\Domain\Service\TranslationMonthlyBudget;
use App\Modules\Generation\Infrastructure\Adapter\DeepLTranslator;
use App\Modules\Generation\Infrastructure\Adapter\FakeTranslator;
use App\Modules\Generation\Infrastructure\Adapter\UnavailableTranslator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/** Bind the counting translator and hand it back, so a test can assert the vendor was NOT called. */
function fakeTranslator(): FakeTranslator
{
    $fake = new FakeTranslator();
    // `app()` and not `$ctx->app`: the container property is protected, and a free function shared
    // across test files (see tests/Pest.php's note) is outside the TestCase's scope.
    app()->instance(TranslationProvider::class, $fake);

    return $fake;
}

/** @return array<string, mixed> */
function instant(object $ctx, string $token, string $query): array
{
    return $ctx->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/search/instant?q=' . urlencode($query))
        ->assertOk()
        ->json('data');
}

it('answers from OUR OWN catalogue first, without touching the vendor', function () {
    $fake = fakeTranslator();
    [$user, $token] = learner();
    seedCollectionWith($user, 'invoice', 'счёт');

    $hint = instant($this, $token, 'Invoice');

    // The catalogue's own string, not a machine translation of it: this is what the card will say
    // if the learner saves the word, and a hint that disagreed with its own card would be worse
    // than none.
    expect($hint['translation'])->toBe('счёт')
        ->and($hint['source'])->toBe('vocabulary');
    expect($fake->calls)->toBe(0);
    // Nothing bought means nothing metered.
    expect(DB::table('instant_translations')->count())->toBe(0);
});

it('buys a word once and serves every later ask from the cache', function () {
    $fake = fakeTranslator();
    [, $token] = learner();
    [, $otherToken] = learner();

    $first = instant($this, $token, 'reimbursement');
    expect($first['source'])->toBe('deepl')->and($fake->calls)->toBe(1);

    // A DIFFERENT user, the same word, and a different spelling of the same query: the answer is a
    // fact about the WORD, so nobody pays for it twice.
    $second = instant($this, $otherToken, '  Reimbursement ');

    expect($second['translation'])->toBe($first['translation'])
        ->and($second['source'])->toBe('cache');
    expect($fake->calls)->toBe(1, 'a cache hit must never reach the vendor');
    expect(DB::table('instant_translations')->count())->toBe(1);
});

it('meters the characters it SENT, which is what the plan bills', function () {
    fakeTranslator();
    [, $token] = learner();

    instant($this, $token, 'Reimbursement');

    $row = DB::table('instant_translations')->first();
    expect($row->provider)->toBe(DeepLTranslator::NAME)
        // 13, the normalized query — not the (longer) Russian reply, and not the raw input.
        ->and((int) $row->characters)->toBe(mb_strlen('reimbursement'))
        ->and((string) $row->lang_pair)->toBe('en:ru');
});

it('reports itself disabled when no provider is configured, and breaks nothing', function () {
    $this->app->instance(TranslationProvider::class, new UnavailableTranslator());
    [, $token] = learner();

    $hint = instant($this, $token, 'reimbursement');

    expect($hint['feature_disabled'])->toBeTrue()
        ->and($hint['translation'])->toBeNull()
        ->and($hint['limit_reached'])->toBeFalse();

    // …and the rest of search is untouched — the hint is a garnish, not a dependency.
    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/search?q=reimbursement')->assertOk();
});

it('stops buying once the month is 95% spent', function () {
    $fake = fakeTranslator();
    // A tiny plan, so the budget is reachable without writing half a million characters.
    $this->app->instance(TranslationMonthlyBudget::class, new TranslationMonthlyBudget(100));
    [, $token] = learner();

    // 95 of the 100 characters already gone this month.
    DB::table('instant_translations')->insert([
        'id' => \App\Modules\Shared\Domain\ValueObject\Ulid::generate(),
        'normalized_text' => 'spent',
        'lang_pair' => 'en:ru',
        'translation' => 'потрачено',
        'provider' => DeepLTranslator::NAME,
        'characters' => 95,
        'created_at' => now(),
    ]);

    $hint = instant($this, $token, 'reimbursement');

    expect($hint['limit_reached'])->toBeTrue()
        ->and($hint['translation'])->toBeNull()
        ->and($hint['feature_disabled'])->toBeFalse();
    expect($fake->calls)->toBe(0, 'the vendor must never be reached past the budget');
});

it('still serves a CACHED word after the budget is spent — it costs nothing', function () {
    $fake = fakeTranslator();
    [, $token] = learner();

    instant($this, $token, 'reimbursement');          // buys and caches it
    $this->app->instance(TranslationMonthlyBudget::class, new TranslationMonthlyBudget(1));

    $hint = instant($this, $token, 'reimbursement');

    // Withholding a translation we already own, to enforce a limit on money that is not being
    // spent, would punish the learner for a bill nobody is paying.
    expect($hint['translation'])->not->toBeNull()
        ->and($hint['source'])->toBe('cache')
        ->and($hint['limit_reached'])->toBeFalse();
    expect($fake->calls)->toBe(1);
});

it('keeps the full lookup working when the hint is out of budget', function () {
    fakeTranslator();
    $this->app->instance(TranslationMonthlyBudget::class, new TranslationMonthlyBudget(0));
    [, $token] = learner();

    expect(instant($this, $token, 'reimbursement')['limit_reached'])->toBeTrue();

    // Different vendor, different budget, different feature. One running out must not take the
    // other down with it.
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/search/lookup', ['query' => 'reimbursement'])
        ->assertOk()
        ->assertJsonPath('data.limit_reached', false);
});

it('says nothing at all for an empty query, and buys nothing', function () {
    $fake = fakeTranslator();
    [, $token] = learner();

    $hint = instant($this, $token, '   ');

    expect($hint['translation'])->toBeNull()->and($hint['source'])->toBeNull();
    expect($fake->calls)->toBe(0);
});

it('shows an empty line rather than an error when the vendor falls over', function () {
    // The one thing this feature must never do is interrupt somebody who is typing.
    $this->app->instance(TranslationProvider::class, new class implements TranslationProvider
    {
        public function isAvailable(): bool
        {
            return true;
        }

        public function name(): string
        {
            return DeepLTranslator::NAME;
        }

        public function translate(string $text, string $source, string $target): never
        {
            throw new RuntimeException('DeepL is down');
        }
    });
    [, $token] = learner();

    $hint = instant($this, $token, 'reimbursement');

    expect($hint['translation'])->toBeNull()
        ->and($hint['feature_disabled'])->toBeFalse()
        ->and($hint['limit_reached'])->toBeFalse();
    expect(DB::table('instant_translations')->count())->toBe(0);
});
