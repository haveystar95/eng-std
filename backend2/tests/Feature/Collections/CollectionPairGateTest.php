<?php

declare(strict_types=1);

use App\Modules\Collections\Application\Command\AddTermToCollection;
use App\Modules\Collections\Application\Command\AddTermToCollectionHandler;
use App\Modules\Collections\Application\Command\CreateCustomCollection;
use App\Modules\Collections\Application\Command\CreateCustomCollectionHandler;
use App\Modules\Collections\Application\Command\MoveTermBetweenCollections;
use App\Modules\Collections\Application\Command\MoveTermBetweenCollectionsHandler;
use App\Modules\Collections\Application\Port\CollectionCurator;
use App\Modules\Collections\Domain\Exception\TermLanguageMismatch;
use App\Modules\Shared\Domain\ValueObject\CollectionId;
use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * «Одна папка — одна пара» (DECISIONS пп. 81, 141): a collection accepts only terms of the language
 * it teaches, and every writer goes through the same gate.
 *
 * The fixtures always pair an `en` folder with a `pl` word, because that is the case that is not
 * hypothetical: the dev database already holds 36 Polish terms beside 659 English ones.
 */
function deck(App\Modules\Identity\Infrastructure\Eloquent\User $user, string $studied, string $title): string
{
    return app(CreateCustomCollectionHandler::class)(new CreateCustomCollection(
        UserId::fromString($user->id), $title, new LanguageCode('ru'), new LanguageCode($studied),
    ))->value;
}

it('refuses a foreign-pair term added by id, with a code the client can show', function () {
    [$user, $token] = learner();
    $english = deck($user, 'en', 'English');
    $polish = deck($user, 'pl', 'Polski');
    $polishTerm = addWordTo($polish, $user->id, 'okno', 'окно');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/collections/{$english}/items", ['term_id' => $polishTerm])
        ->assertStatus(422)
        ->assertJsonPath('code', 'term_language_mismatch')
        ->assertJsonPath('meta.expected_lang', 'en')
        ->assertJsonPath('meta.actual_lang', 'pl');

    // …and nothing was written on the way out.
    $this->assertDatabaseMissing('collection_items', ['collection_id' => $english, 'term_id' => $polishTerm]);
});

it('refuses a foreign-pair term on the shared Application door every writer uses', function () {
    // Generation, the recovery command and the save-from-search all reach a folder through this one
    // handler, so covering it covers them — the gate is in the aggregate below it, not in any of them.
    [$user] = learner();
    $english = deck($user, 'en', 'English');
    $polish = deck($user, 'pl', 'Polski');
    $polishTerm = addWordTo($polish, $user->id, 'okno', 'окно');

    expect(fn () => app(AddTermToCollectionHandler::class)(new AddTermToCollection(
        CollectionId::fromString($english),
        TermId::fromString($polishTerm),
        UserId::fromString($user->id),
    )))->toThrow(TermLanguageMismatch::class);
});

it('refuses a move into a folder of another pair and leaves the word where it was', function () {
    [$user] = learner();
    $english = deck($user, 'en', 'English');
    $polish = deck($user, 'pl', 'Polski');
    $polishTerm = addWordTo($polish, $user->id, 'okno', 'окно');

    expect(fn () => app(MoveTermBetweenCollectionsHandler::class)(new MoveTermBetweenCollections(
        CollectionId::fromString($polish),
        CollectionId::fromString($english),
        TermId::fromString($polishTerm),
        UserId::fromString($user->id),
    )))->toThrow(TermLanguageMismatch::class);

    // The refusal fires inside the transaction and before the removal — a rejected move must not
    // cost the learner the word.
    $this->assertDatabaseHas('collection_items', [
        'collection_id' => $polish, 'term_id' => $polishTerm, 'deleted_at' => null,
    ]);
});

it('refuses a foreign-pair term in the back-office curator too, which writes items directly', function () {
    [$user] = learner();
    $english = deck($user, 'en', 'English');
    $polish = deck($user, 'pl', 'Polski');
    $polishTerm = addWordTo($polish, $user->id, 'okno', 'окно');

    expect(fn () => app(CollectionCurator::class)->addTerm(
        CollectionId::fromString($english),
        TermId::fromString($polishTerm),
    ))->toThrow(TermLanguageMismatch::class);
});

it('lets a matching term through every door', function () {
    [$user, $token] = learner();
    $english = deck($user, 'en', 'English');
    $other = deck($user, 'en', 'Other English');
    $englishTerm = addWordTo($other, $user->id, 'window', 'окно');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/collections/{$english}/items", ['term_id' => $englishTerm])
        ->assertCreated();

    expect(app(CollectionCurator::class)->addTerm(
        CollectionId::fromString($english),
        TermId::fromString($englishTerm),
    ))->toBeTrue();
});

it('creates a collection with the pair the client named', function () {
    [$user, $token] = learner();
    profileFor($user, ['native_language' => 'ru', 'target_language' => 'en']);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/collections', ['title' => 'Polski', 'source_lang' => 'uk', 'target_lang' => 'pl'])
        ->assertCreated()
        ->assertJsonPath('data.source_lang', 'uk')
        ->assertJsonPath('data.target_lang', 'pl');
});

it('creates a collection with the PROFILE pair when the client names none', function () {
    [$user, $token] = learner();
    profileFor($user, ['native_language' => 'uk', 'target_language' => 'pl']);

    // Was `ru→en` literals in the controller before: the profile pickers existed and decided nothing
    // (DECISIONS п. 142).
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/collections', ['title' => 'Без пары'])
        ->assertCreated()
        ->assertJsonPath('data.source_lang', 'uk')
        ->assertJsonPath('data.target_lang', 'pl');
});

it('creates «Сохранённые» with the profile pair, not ru→en', function () {
    [$user, $token] = learner();
    profileFor($user, ['native_language' => 'uk', 'target_language' => 'pl']);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/collections/default')
        ->assertOk()
        ->assertJsonPath('data.is_default', true)
        ->assertJsonPath('data.source_lang', 'uk')
        ->assertJsonPath('data.target_lang', 'pl');
});
