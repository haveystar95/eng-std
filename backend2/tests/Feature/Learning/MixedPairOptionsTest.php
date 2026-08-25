<?php

declare(strict_types=1);

use App\Modules\Collections\Application\Command\AddWordToCollection;
use App\Modules\Collections\Application\Command\AddWordToCollectionHandler;
use App\Modules\Collections\Application\Command\CreateCustomCollection;
use App\Modules\Collections\Application\Command\CreateCustomCollectionHandler;
use App\Modules\Learning\Application\Command\EnrollTerm;
use App\Modules\Learning\Application\Command\EnrollTermHandler;
use App\Modules\Shared\Domain\ValueObject\CollectionId;
use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * THE PAIR GATE ON THE OPTION POOL — reported from the owner's phone, 26.08.
 *
 * A pool session mixes collections of different pairs by design (ru→en, ru→es, ru→it, ru→pl), and
 * the option pool did not know that. Shown «привет», the card offered `hello`, `hola` and `ciao`:
 * three correct translations, one of which the answer key names. A learner who knows the word taps
 * a right answer and is marked wrong — the worst shape a card can have, because it punishes exactly
 * the knowledge it was asked to measure.
 *
 * The rule: an option for a card of pair X→Y comes from terms of pair X→Y and from nowhere else.
 * A pair is DIRECTED — ru→en and en→ru have the same two languages and are not the same pair, and
 * their terms may not be swapped either.
 */
function seedPaired(string $collectionId, string $userId, string $text, string $translation): string
{
    $termId = app(AddWordToCollectionHandler::class)(new AddWordToCollection(
        CollectionId::fromString($collectionId),
        UserId::fromString($userId),
        $text,
        $translation,
        type: 'word',
    ))->value;

    app(EnrollTermHandler::class)(new EnrollTerm(UserId::fromString($userId), TermId::fromString($termId)));

    return $termId;
}

/**
 * @param  list<array{0: string, 1: string}>  $words
 * @return list<string>  the seeded term ids
 */
function seedPair(object $user, string $source, string $target, string $name, array $words): array
{
    $collectionId = app(CreateCustomCollectionHandler::class)(new CreateCustomCollection(
        UserId::fromString($user->id), $name, new LanguageCode($source), new LanguageCode($target),
    ))->value;

    return array_map(
        static fn (array $w): string => seedPaired($collectionId, $user->id, $w[0], $w[1]),
        $words,
    );
}

it('never offers another pair language as an option in a mixed session', function () {
    [$user, $token] = learner();

    // The reported deck, in miniature: one greeting per pair, and each pair too thin to fill a card
    // on its own. That is the shape the bug needs — the same-topic preference runs out, the pool
    // widens, and before this fix it widened into the neighbouring LANGUAGES.
    $en = seedPair($user, 'ru', 'en', 'Английский', [
        ['hello', 'привет'], ['table', 'стол'],
    ]);
    $es = seedPair($user, 'ru', 'es', 'Испанский', [
        ['hola', 'привет'], ['silla', 'стул'],
    ]);
    $it = seedPair($user, 'ru', 'it', 'Итальянский', [
        ['ciao', 'привет'], ['pane', 'хлеб'],
    ]);

    $cards = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/study/sessions', ['limit' => 40])
        ->assertOk()
        ->json('data.cards');
    expect($cards)->not->toBe([]);

    // What each pair is allowed to show, on either side of the card: its own terms and its own
    // translations. Anything else on a card of that pair came from a neighbouring language.
    $allowed = [
        'en' => ['hello', 'table', 'привет', 'стол'],
        'es' => ['hola', 'silla', 'привет', 'стул'],
        'it' => ['ciao', 'pane', 'привет', 'хлеб'],
    ];
    $pairOf = [];
    foreach (['en' => $en, 'es' => $es, 'it' => $it] as $lang => $ids) {
        foreach ($ids as $id) {
            $pairOf[$id] = $lang;
        }
    }

    $withOptions = 0;
    foreach ($cards as $card) {
        if (($card['options'] ?? null) === null) {
            continue;
        }
        $withOptions++;
        $lang = $pairOf[$card['term_id']];
        foreach ($card['options'] as $option) {
            expect(in_array($option, $allowed[$lang], true))->toBe(
                true,
                "a {$lang} card was offered «{$option}» from another pair",
            );
        }
    }
    expect($withOptions)->toBeGreaterThan(0, 'the session must actually deal option cards');
});

it('does not swap options between sibling pairs of the same two languages', function () {
    [$user, $token] = learner();

    // en→de and de→en: the same two languages, opposite directions. (Not ru↔en, because ru is a
    // reference language and its words are never enrolled — DECISIONS пп. 84, 136.) The German words
    // of one deck are the TERMS; of the other they are the translations, and a card of either must
    // not reach into the other.
    $forward = seedPair($user, 'en', 'de', 'English → Deutsch', [
        ['Tisch', 'table'], ['Wasser', 'water'],
    ]);
    $back = seedPair($user, 'de', 'en', 'Deutsch → English', [
        ['chair', 'Stuhl'], ['milk', 'Milch'],
    ]);

    $cards = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/study/sessions', ['limit' => 40])
        ->assertOk()
        ->json('data.cards');

    $allowed = [
        'fwd' => ['Tisch', 'Wasser', 'table', 'water'],
        'back' => ['chair', 'milk', 'Stuhl', 'Milch'],
    ];
    $pairOf = [];
    foreach (['fwd' => $forward, 'back' => $back] as $side => $ids) {
        foreach ($ids as $id) {
            $pairOf[$id] = $side;
        }
    }

    $withOptions = 0;
    foreach ($cards as $card) {
        if (($card['options'] ?? null) === null) {
            continue;
        }
        $withOptions++;
        $side = $pairOf[$card['term_id']];
        foreach ($card['options'] as $option) {
            expect(in_array($option, $allowed[$side], true))->toBe(
                true,
                "a {$side} card was offered «{$option}» from its sibling pair",
            );
        }
    }
    expect($withOptions)->toBeGreaterThan(0);
});

it('deals fewer options rather than one from another pair', function () {
    [$user, $token] = learner();

    // The pair floor, the same rule the shape filter already follows: the Spanish deck has exactly
    // one same-pair neighbour, so its cards are two options wide. The five English words sitting in
    // the same session are not allowed to fill the gap.
    seedPair($user, 'ru', 'en', 'Английский', [
        ['hello', 'привет'], ['table', 'стол'], ['water', 'вода'], ['book', 'книга'], ['house', 'дом'],
    ]);
    $es = seedPair($user, 'ru', 'es', 'Испанский', [
        ['hola', 'привет'], ['silla', 'стул'],
    ]);

    $cards = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/study/sessions', ['limit' => 40])
        ->assertOk()
        ->json('data.cards');

    $spanish = array_values(array_filter(
        $cards,
        static fn (array $c): bool => in_array($c['term_id'], $es, true) && ($c['options'] ?? null) !== null,
    ));
    expect($spanish)->not->toBe([], 'the Spanish deck must still be dealt cards');

    foreach ($spanish as $card) {
        expect($card['options'])->toHaveCount(2, 'answer + the one same-pair neighbour');
        foreach ($card['options'] as $option) {
            expect(in_array($option, ['hola', 'silla', 'привет', 'стул'], true))->toBe(
                true,
                "the Spanish card was offered «{$option}»",
            );
        }
    }
});

it('leaves a single-pair session exactly as it was', function () {
    // The regression guard: nothing above may cost a one-pair session its full-width cards.
    [$user, $token] = learner();
    $en = seedPair($user, 'ru', 'en', 'Английский', [
        ['hello', 'привет'], ['table', 'стол'], ['water', 'вода'], ['book', 'книга'], ['house', 'дом'],
    ]);

    $cards = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/study/sessions', ['limit' => 40])
        ->assertOk()
        ->json('data.cards');

    $recognition = array_values(array_filter(
        $cards,
        static fn (array $c): bool => in_array($c['ladder_step'] ?? null, [1, 2], true) && ($c['options'] ?? null) !== null,
    ));
    expect($recognition)->not->toBe([]);
    expect(count($en))->toBe(5);

    foreach ($recognition as $card) {
        expect($card['options'])->toHaveCount(4, 'a full recognition card: answer + three neighbours');
    }
});

it('keeps the ordinary multiple_choice pool inside the card own language', function () {
    // The OTHER option builder, the one a graduated term reaches: `DistractorReader`. It already
    // filtered its top-up query by language and did NOT filter the session's own pool — which in a
    // mixed session is exactly the list of foreign-language terms. Same bug, second door.
    [$user] = learner();

    $en = seedPair($user, 'ru', 'en', 'Английский', [
        ['hello', 'привет'], ['table', 'стол'],
    ]);
    $es = seedPair($user, 'ru', 'es', 'Испанский', [
        ['hola', 'привет'], ['silla', 'стул'], ['leche', 'молоко'],
    ]);

    $pool = array_merge($en, $es);
    $options = app(App\Modules\Vocabulary\Application\Query\DistractorReader::class)
        ->forTarget(TermId::fromString($en[0]), $pool, 3);

    foreach ($options as $option) {
        expect(in_array($option, ['table'], true))->toBe(
            true,
            "an English card was offered «{$option}» from the Spanish deck",
        );
    }
    expect($es)->toHaveCount(3); // guard: the Spanish words really are in the pool
});
