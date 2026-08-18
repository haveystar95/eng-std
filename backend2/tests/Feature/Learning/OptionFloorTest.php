<?php

declare(strict_types=1);

use App\Modules\Collections\Application\Command\CreateCustomCollection;
use App\Modules\Collections\Application\Command\CreateCustomCollectionHandler;
use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * QA-15: a choice card with ONE option is not a card.
 *
 * On the device, «Как вы справляетесь с конфликтами?» was dealt with a single option — the answer —
 * and tapping the only thing on screen wrote a correct answer into an append-only log for a
 * retrieval that never happened.
 *
 * The minimum was checked in the branches that could think of it and not where they LAND. The
 * recognition card refuses itself when it cannot find same-shape neighbours, and falls through to
 * ordinary multiple_choice; that branch asks the distractor reader for three wrong answers and
 * takes however many it gets. For a term with no same-language neighbours at all, that is none.
 *
 * So the floor lives after every branch, in one place, and this deck is the case that walks every
 * fallback in turn: one phrase, alone in its language, with nothing to be offered beside it.
 */

/** The repro deck: a single phrase, so there is no neighbour and no distractor anywhere. */
function loneliestDeck(object $user): string
{
    $actor = UserId::fromString($user->id);
    $collectionId = app(CreateCustomCollectionHandler::class)(new CreateCustomCollection(
        $actor, 'One question', new LanguageCode('ru'), new LanguageCode('en'),
    ))->value;

    addWordTo($collectionId, $user->id, 'How do you deal with conflict?', 'Как вы справляетесь с конфликтами?');

    return $collectionId;
}

it('never deals a choice card with fewer than two options', function () {
    [$user, $token] = learner();
    $collectionId = loneliestDeck($user);

    $cards = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/study/sessions', ['collection_id' => $collectionId, 'size' => 40])
        ->assertOk()
        ->json('data.cards');

    foreach ($cards as $card) {
        if ($card['options'] === null) {
            continue; // not a choice card — typing, word_bank, the intro
        }
        expect(count($card['options']))->toBeGreaterThanOrEqual(
            2,
            "«{$card['prompt']}» was dealt as {$card['exercise_mode']} with one option",
        );
    }
});

it('deals nothing at all rather than one unanswerable card', function () {
    [$user, $token] = learner();
    $collectionId = loneliestDeck($user);

    $data = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/study/sessions', ['collection_id' => $collectionId, 'size' => 40])
        ->assertOk()
        ->json('data');

    // The honest end of this: a brand-new word is owed its recognition rungs, and rungs 1–2 admit
    // multiple_choice and nothing else — so a deck with no neighbour to offer beside the answer has
    // no card to deal. The session comes back empty, which the screen already renders as its empty
    // state, and the alternative is the tap that logged a retrieval that never happened.
    //
    // It bites here and only here: the moment the deck has a second term, or the pair reaches the
    // graduated rungs where the option-free trainers open, there is a card again — the two tests
    // either side of this one.
    expect($data['cards'])->toBe([]);
});

it('leaves the option-free trainers alone — a term with an example is still trained', function () {
    [$user, $token] = learner();
    $collectionId = loneliestDeck($user);
    $termId = DB::table('collection_items')->where('collection_id', $collectionId)->value('term_id');

    // The same lonely deck, one rung up and with the example the rotation at rung 3 asks for.
    // cloze/scramble ask for the term or its sentence and build no options at all, so the floor
    // never applies and the word is trained exactly as before.
    DB::table('term_examples')->insert([
        'id' => \App\Modules\Shared\Domain\ValueObject\Ulid::generate(),
        'term_id' => $termId,
        'sentence' => 'How do you deal with conflict at work?',
        'sentence_translation' => 'Как вы справляетесь с конфликтами на работе?',
        'source' => 'ai', 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('user_term_progress')->insert([
        'user_id' => $user->id, 'term_id' => $termId,
        'state' => 'review', 'acquisition' => 'graduated', 'learning_step' => 0,
        'reps' => 2, 'successful_reviews' => 2, 'lapses' => 0,
        'ease_factor' => 2.5, 'interval_days' => 5, 'due_at' => now()->subDay(),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $cards = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/study/sessions', ['collection_id' => $collectionId, 'size' => 40])
        ->assertOk()
        ->json('data.cards');

    expect($cards)->not->toBe([])
        ->and($cards[0]['options'])->toBeNull();
});

it('still deals the card once there is something to offer beside the answer', function () {
    // The same deck plus one neighbour of the same shape — the floor must gate the empty case only,
    // not become a reason phrases stop being asked.
    [$user, $token] = learner();
    $collectionId = loneliestDeck($user);
    addWordTo($collectionId, $user->id, 'Can we talk about it later?', 'Можем обсудить это позже?');

    $cards = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/study/sessions', ['collection_id' => $collectionId, 'size' => 40])
        ->assertOk()
        ->json('data.cards');

    $choice = array_values(array_filter($cards, static fn (array $c): bool => $c['options'] !== null));

    expect($choice)->not->toBe([], 'a two-phrase deck can build a choice card');
    foreach ($choice as $card) {
        expect(count($card['options']))->toBeGreaterThanOrEqual(2);
    }
});
