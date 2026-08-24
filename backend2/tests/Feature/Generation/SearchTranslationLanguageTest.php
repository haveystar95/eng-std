<?php

declare(strict_types=1);

use App\Modules\Generation\Application\Port\WordLookupPort;
use App\Modules\Generation\Infrastructure\Adapter\FakeWordLookup;
use App\Modules\Identity\Infrastructure\Eloquent\User;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->app->bind(WordLookupPort::class, FakeWordLookup::class);
});

/**
 * A SEARCH HIT ANSWERS THE PAIR IT WAS ASKED IN — or it answers with nothing (DECISIONS п. 146).
 *
 * The bug this pins is live: `invoice`, searched in RU → EN, came back glossed «factură». The term
 * had picked up a Romanian translation through a different pair, and the reader's fallback («any
 * translation beats none») served it as if it were the answer. On a card that fallback is right —
 * the alternative there is a card with no question on it — but in search the alternative is one tap
 * on «Найти с ИИ», which answers in the pair for real.
 */
function termWithTranslations(User $user, string $text, array $translations): string
{
    [, $termId] = seedCollectionWith($user, $text, $translations['ru'] ?? 'x', enroll: false);

    if (! isset($translations['ru'])) {
        DB::table('term_translations')->where('term_id', $termId)->delete();
    }

    foreach ($translations as $lang => $value) {
        if ($lang === 'ru') {
            continue;
        }
        DB::table('term_translations')->insert([
            'id' => Ulid::generate(),
            'term_id' => $termId,
            'lang' => $lang,
            'text' => $value,
            'is_primary' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    return $termId;
}

it('does not dress a hit in a translation from another pair', function () {
    [$user, $token] = learner();
    termWithTranslations($user, 'invoice', ['ro' => 'factură']);

    $hit = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/search?q=invoice&source=en&target=ru')
        ->assertOk()->json('data.0');

    // The word is still found — it is the same English term either way. Only the gloss is missing,
    // and missing is the honest state: the client renders no line and the paid lookup fills it in.
    expect($hit['text'])->toBe('invoice')
        ->and($hit['translation'])->toBeNull();
});

it('shows the translation when it IS in the pair', function () {
    [$user, $token] = learner();
    termWithTranslations($user, 'invoice', ['ro' => 'factură']);

    $hit = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/search?q=invoice&source=en&target=ro')
        ->assertOk()->json('data.0');

    expect($hit['translation'])->toBe('factură');
});

it('shows neither of two foreign glosses to a third pair', function () {
    [$user, $token] = learner();
    termWithTranslations($user, 'invoice', ['ru' => 'счёт', 'ro' => 'factură']);

    // Support is Spanish. The term has Russian and Romanian rows and nothing in Spanish, so the
    // hit carries no translation at all rather than whichever of the two sorted first.
    $hit = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/search?q=invoice&source=es&target=en')
        ->assertOk()->json('data.0');

    expect($hit['text'])->toBe('invoice')
        ->and($hit['translation'])->toBeNull();
});

it('does not gloss an example in a language nobody asked for', function () {
    [$user, $token] = learner();
    $termId = termWithTranslations($user, 'invoice', ['ru' => 'счёт']);
    seedExample([
        'term_id' => $termId,
        'sentence' => 'Please send the invoice.',
        'translation' => 'Vă rog să trimiteți factura.',
        'translation_lang' => 'ro',
    ]);

    $hit = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/search?q=invoice&source=en&target=ru')
        ->assertOk()->json('data.0');

    expect($hit['example'])->toBe('Please send the invoice.')
        ->and($hit['example_translation'])->toBeNull();
});

it('keeps the instant hint inside the pair too', function () {
    $fake = fakeTranslator();
    [$user, $token] = learner();
    termWithTranslations($user, 'invoice', ['ro' => 'factură']);

    $hint = instant($this, $token, 'invoice', 'en', 'ru');

    // Our own catalogue has nothing to say in Russian about this word, so the ladder walks on to
    // the vendor — which is asked in the pair — instead of printing the Romanian row.
    expect($hint['translation'])->not->toBe('factură')
        ->and($hint['source'])->not->toBe('vocabulary');
    expect($fake->directions)->toBe(['en→ru']);
});
