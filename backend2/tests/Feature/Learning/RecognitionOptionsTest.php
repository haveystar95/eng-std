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
 * QA-6: the options on a recognition card must be answerable by KNOWING THE WORD and by nothing
 * else — which they are not when they differ from it in SHAPE.
 *
 * On the device, `grain-free` (a word) was offered «без злаков», «сухой корм», «Где я могу найти
 * корм для собак?» and «Подходит ли это для мелких пород?». Two options are whole questions and
 * obviously cannot translate one word, so discarding them costs no knowledge at all: a one-in-four
 * card becomes a coin toss. The reverse direction was worse — the only SHORT option among three
 * sentences was the right one, readable without reading.
 *
 * A generated collection is 60–70% multi-word by design, so mixed shapes are the normal case here,
 * not an edge one.
 */
function seedTyped(string $collectionId, string $userId, string $text, string $translation, string $type): string
{
    $termId = app(AddWordToCollectionHandler::class)(new AddWordToCollection(
        CollectionId::fromString($collectionId),
        UserId::fromString($userId),
        $text,
        $translation,
        type: $type,
    ))->value;

    // …and into the learner's pool, because every case here is about the cards a SESSION deals.
    app(EnrollTermHandler::class)(new EnrollTerm(UserId::fromString($userId), TermId::fromString($termId)));

    return $termId;
}

/** The dog-food deck from the acceptance run: two words among four sentences. */
function dogFoodDeck(object $user): string
{
    $actor = UserId::fromString($user->id);
    $collectionId = app(CreateCustomCollectionHandler::class)(new CreateCustomCollection(
        $actor, 'Buying Dog Food', new LanguageCode('ru'), new LanguageCode('en'),
    ))->value;

    seedTyped($collectionId, $user->id, 'grain-free', 'без злаков', 'word');
    seedTyped($collectionId, $user->id, 'organic', 'органический', 'word');
    seedTyped($collectionId, $user->id, 'Where can I find dog food?', 'Где я могу найти корм для собак?', 'phrase');
    seedTyped($collectionId, $user->id, 'Is this suitable for small breeds?', 'Подходит ли это для мелких пород?', 'phrase');
    seedTyped($collectionId, $user->id, 'How much does this bag cost?', 'Сколько стоит этот пакет?', 'phrase');
    seedTyped($collectionId, $user->id, 'Would you like a receipt?', 'Хотите чек?', 'phrase');

    return $collectionId;
}

it('never mixes shapes in a recognition card options', function () {
    [$user, $token] = learner();
    $collectionId = dogFoodDeck($user);

    $cards = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/study/sessions', ['collection_id' => $collectionId, 'size' => 40])
        ->assertOk()
        ->json('data.cards');

    // Every option on every recognition card belongs to a term of the card's own type. Checked by
    // TEXT rather than by id, because the reverse direction sends no option ids at all.
    $wordSide = ['grain-free', 'organic', 'без злаков', 'органический'];

    $recognition = array_values(array_filter(
        $cards,
        static fn (array $c): bool => in_array($c['ladder_step'] ?? null, [1, 2], true) && $c['options'] !== null,
    ));
    expect($recognition)->not->toBe([], 'the deck is all new, so the session is recognition cards');

    foreach ($recognition as $card) {
        $isWordCard = $card['type'] === 'word';
        foreach ($card['options'] as $option) {
            expect(in_array($option, $wordSide, true))->toBe(
                $isWordCard,
                "a {$card['type']} card was offered «{$option}»",
            );
        }
    }
});

it('deals FEWER options rather than padding with a different shape', function () {
    [$user, $token] = learner();
    $actor = UserId::fromString($user->id);
    $collectionId = app(CreateCustomCollectionHandler::class)(new CreateCustomCollection(
        $actor, 'Two words, four sentences', new LanguageCode('ru'), new LanguageCode('en'),
    ))->value;

    // One word has exactly ONE same-shape neighbour. Three options would need three, and the answer
    // to that is a two-option card — not four options two of which give themselves away.
    seedTyped($collectionId, $user->id, 'grain-free', 'без злаков', 'word');
    seedTyped($collectionId, $user->id, 'organic', 'органический', 'word');
    seedTyped($collectionId, $user->id, 'Where can I find dog food?', 'Где я могу найти корм для собак?', 'phrase');
    seedTyped($collectionId, $user->id, 'Is this suitable for small breeds?', 'Подходит ли это для мелких пород?', 'phrase');

    $cards = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/study/sessions', ['collection_id' => $collectionId, 'size' => 40])
        ->assertOk()
        ->json('data.cards');

    $wordCards = array_values(array_filter(
        $cards,
        static fn (array $c): bool => $c['type'] === 'word' && in_array($c['ladder_step'] ?? null, [1, 2], true),
    ));
    expect($wordCards)->not->toBe([]);

    foreach ($wordCards as $card) {
        expect($card['options'])->toHaveCount(2, 'answer + the one same-shape neighbour');
    }
});

it('falls back to an ordinary card when no neighbour shares the shape', function () {
    [$user, $token] = learner();
    $actor = UserId::fromString($user->id);
    $collectionId = app(CreateCustomCollectionHandler::class)(new CreateCustomCollection(
        $actor, 'One word among sentences', new LanguageCode('ru'), new LanguageCode('en'),
    ))->value;

    // The lone word has nothing of its own shape to stand beside. A one-option card is not a card,
    // so the assembler falls through to ordinary multiple_choice — which builds its options from the
    // enrichment distractors and the wider pool, not from the ladder's far ones.
    $lonely = seedTyped($collectionId, $user->id, 'grain-free', 'без злаков', 'word');
    seedTyped($collectionId, $user->id, 'Where can I find dog food?', 'Где я могу найти корм для собак?', 'phrase');
    seedTyped($collectionId, $user->id, 'Is this suitable for small breeds?', 'Подходит ли это для мелких пород?', 'phrase');

    $cards = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/study/sessions', ['collection_id' => $collectionId, 'size' => 40])
        ->assertOk()
        ->json('data.cards');

    $lonelyCards = array_values(array_filter($cards, static fn (array $c): bool => $c['term_id'] === $lonely));
    expect($lonelyCards)->not->toBe([]);

    foreach ($lonelyCards as $card) {
        // The tell of the far-option card is `option_ids` on the forward rung; falling through drops
        // it, and the card is then graded against the term's own text like any other.
        expect($card['option_ids'] ?? null)->toBeNull();
        expect($card['answer'])->toBe('grain-free');
    }
});

it('prefers far options from the card own topic when the pool mixes collections', function () {
    // A pool session is no longer one collection's words. «аптека» beside «собеседование» beside
    // «аэропорт» makes a far option far by SUBJECT, and the learner picks the pharmacy-shaped word
    // without knowing it — so same-topic neighbours come first, and the other topics only fill in.
    [$user, $token] = learner();
    $actor = UserId::fromString($user->id);

    $pharmacy = app(CreateCustomCollectionHandler::class)(new CreateCustomCollection(
        $actor, 'Аптека', new LanguageCode('ru'), new LanguageCode('en'),
    ))->value;
    $interview = app(CreateCustomCollectionHandler::class)(new CreateCustomCollection(
        $actor, 'Собеседование', new LanguageCode('ru'), new LanguageCode('en'),
    ))->value;

    $target = seedTyped($pharmacy, $user->id, 'antipyretic', 'жаропонижающее', 'word');
    $sameTopic = [
        seedTyped($pharmacy, $user->id, 'painkiller', 'обезболивающее', 'word'),
        seedTyped($pharmacy, $user->id, 'prescription', 'рецепт', 'word'),
        seedTyped($pharmacy, $user->id, 'pharmacist', 'фармацевт', 'word'),
    ];
    // Plenty of other-topic words, so a preference that did nothing would show up as a mix.
    foreach ([['resume', 'резюме'], ['vacancy', 'вакансия'], ['salary', 'зарплата'], ['notice', 'уведомление']] as [$en, $ru]) {
        seedTyped($interview, $user->id, $en, $ru, 'word');
    }

    $cards = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/study/sessions', ['limit' => 40])
        ->assertOk()
        ->json('data.cards');

    $own = array_values(array_filter(
        $cards,
        static fn (array $c): bool => $c['term_id'] === $target && in_array($c['ladder_step'] ?? null, [1, 2], true),
    ));
    expect($own)->not->toBe([], 'the target is a first meeting, so it is dealt recognition cards');

    $pharmacyWords = ['antipyretic', 'жаропонижающее', 'painkiller', 'обезболивающее',
        'prescription', 'рецепт', 'pharmacist', 'фармацевт'];
    foreach ($own as $card) {
        // Three same-topic neighbours exist, and a recognition card takes three options — so every
        // option on this card can and must come from the pharmacy.
        foreach ($card['options'] as $option) {
            expect($pharmacyWords)->toContain($option);
        }
    }
    expect($sameTopic)->toHaveCount(3); // guard: the fixture really does offer enough neighbours
});
