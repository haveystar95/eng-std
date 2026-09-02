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
use App\Modules\Shared\Domain\ValueObject\Ulid;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

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

    // …and one catalogue word the TOP-UP can reach, of a comparable length. It is on another shelf,
    // so it is not a neighbour of this session and the far-option path still finds nothing — which
    // is the premise of this test — but the ordinary multiple_choice the card falls to can be built.
    // Without it the card is refused for want of options, and the fall-through goes unobserved.
    $catalogue = Ulid::generate();
    DB::table('collections')->insert([
        'id' => $catalogue, 'owner_id' => null, 'type' => 'system', 'source' => 'curated',
        'title' => 'Витрина', 'source_lang' => 'ru', 'target_lang' => 'en', 'visibility' => 'public',
        'items_count' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $filler = Ulid::generate();
    DB::table('terms')->insert([
        'id' => $filler, 'lang' => 'en', 'text' => 'wheat-free', 'normalized_text' => 'wheat-free',
        // `kind` NULL, like the rest of the catalogue: the family rule is «null against null», and
        // a plan's `word` is a different family from ordinary vocabulary ({@see DistractorFamily}).
        'type' => 'word', 'source' => 'curated', 'cefr' => 'A2',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('term_translations')->insert([
        'id' => Ulid::generate(), 'term_id' => $filler, 'lang' => 'ru', 'text' => 'без пшеницы',
        'is_primary' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('collection_items')->insert([
        'id' => Ulid::generate(), 'collection_id' => $catalogue, 'term_id' => $filler,
        'position' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);

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

it('reaches the catalogue rather than shrinking a starved choice to two (Д-36)', function () {
    // The live shape, and the reason it is a fact about the ORDINARY session: a running plan holds
    // its own words out of the queue, so the session the learner is left with can be two words wide.
    // «cold» came out with a single wrong answer — a coin toss, and a correct answer written into an
    // append-only log for a retrieval that never happened (скрины 193, 197).
    [$user, $token] = learner();
    $actor = UserId::fromString($user->id);

    $mine = app(CreateCustomCollectionHandler::class)(new CreateCustomCollection(
        $actor, 'Простуда', new LanguageCode('ru'), new LanguageCode('en'),
    ))->value;

    // The session's own belt: the target and ONE neighbour. One short of the floor of three.
    $target = seedTyped($mine, $user->id, 'cold', 'простуда', 'word');
    seedTyped($mine, $user->id, 'cough', 'кашель', 'word');

    // …and a catalogue that can easily furnish the rest, in band and of the same shape.
    $catalogue = app(CreateCustomCollectionHandler::class)(new CreateCustomCollection(
        $actor, 'Витрина', new LanguageCode('ru'), new LanguageCode('en'),
    ))->value;
    foreach ([['rash', 'сыпь'], ['ache', 'ломота'], ['pill', 'таблетка']] as [$en, $ru]) {
        $id = app(AddWordToCollectionHandler::class)(new AddWordToCollection(
            CollectionId::fromString($catalogue), $actor, $en, $ru, type: 'word',
        ))->value;
        // Catalogue material, not the learner's own writing — and NOT enrolled, so it is not part of
        // the session's own belt. The only way it can reach the card is the top-up.
        DB::table('terms')->where('id', $id)->update(['source' => 'ai']);
    }

    $cards = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/study/sessions', ['collection_id' => $mine, 'size' => 40])
        ->assertOk()
        ->json('data.cards');

    $choices = array_values(array_filter(
        $cards,
        static fn (array $c): bool => $c['term_id'] === $target && ($c['options'] ?? null) !== null,
    ));

    expect($choices)->not->toBe([], 'the target must still be dealt its recognition cards');
    foreach ($choices as $card) {
        expect(count($card['options']))->toBeGreaterThanOrEqual(
            3,
            'a two-option card is a coin toss: ' . implode(' / ', $card['options']),
        );
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
    // All three Russian sides sit inside «жаропонижающее»'s length band, and that is deliberate:
    // since Д-2 the band is measured on the text the card SHOWS, which on a forward recognition
    // card is the translation. «рецепт» (6) against «жаропонижающее» (14) is outside it, so with it
    // in the fixture this test measured the band rather than the topic preference it is about.
    $sameTopic = [
        seedTyped($pharmacy, $user->id, 'painkiller', 'обезболивающее', 'word'),
        seedTyped($pharmacy, $user->id, 'antiseptic', 'антисептик', 'word'),
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
        'antiseptic', 'антисептик', 'pharmacist', 'фармацевт'];
    foreach ($own as $card) {
        // Three same-topic neighbours exist, and a recognition card takes three options — so every
        // option on this card can and must come from the pharmacy.
        foreach ($card['options'] as $option) {
            expect($pharmacyWords)->toContain($option);
        }
    }
    expect($sameTopic)->toHaveCount(3); // guard: the fixture really does offer enough neighbours
});
