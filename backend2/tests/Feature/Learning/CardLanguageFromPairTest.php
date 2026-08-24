<?php

declare(strict_types=1);

use App\Modules\Collections\Application\Command\CreateCustomCollection;
use App\Modules\Collections\Application\Command\CreateCustomCollectionHandler;
use App\Modules\Learning\Application\Service\CardLanguageResolver;
use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Shared\Domain\ValueObject\UserId;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * The language a card is written in comes from the COLLECTION's pair, never from the profile
 * (DECISIONS пп. 81, 142; `search-language-pair.md` D11).
 *
 * Every fixture here gives the learner a `ru` profile and puts the word in a NON-`ru` collection,
 * because that is the only shape in which the two answers differ — and it is exactly the shape the
 * old code got wrong: a word saved in an `en→uk` folder was glossed to a `ru` profile in Russian,
 * or, when the term had no Russian row at all, in nothing.
 */
/** A collection of the given pair, with one word carrying a translation in each language given. */
function pairedWord(
    App\Modules\Identity\Infrastructure\Eloquent\User $user,
    string $sourceLang,
    string $text,
    array $translations,
): array {
    $actor = UserId::fromString($user->id);
    $collectionId = app(CreateCustomCollectionHandler::class)(new CreateCustomCollection(
        $actor, "deck-{$sourceLang}-{$text}", new LanguageCode($sourceLang), new LanguageCode('en'),
    ));

    $termId = addWordTo($collectionId->value, $user->id, $text, $translations[$sourceLang]);

    foreach ($translations as $lang => $value) {
        if ($lang === $sourceLang) {
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

    return [$collectionId->value, $termId];
}

/** @return array<string, string|null> term id => the translation /sync shipped for it */
function syncedTranslations(object $ctx, string $token): array
{
    $out = [];
    foreach (sync($ctx, $token)['changes']['terms'] as $term) {
        if (($term['op'] ?? '') === 'upsert') {
            $out[$term['id']] = $term['translation'];
        }
    }

    return $out;
}

it('ships the collection pair language on /sync, not the profile one', function () {
    [$user, $token] = learner();
    $user->profile()->update(['native_language' => 'ru']);

    [, $termId] = pairedWord($user, 'uk', 'apple', ['uk' => 'яблуко', 'ru' => 'яблоко']);

    expect(syncedTranslations($this, $token)[$termId])->toBe('яблуко');
});

it('reads each term in ITS OWN collection language when the shelf mixes pairs', function () {
    [$user, $token] = learner();
    $user->profile()->update(['native_language' => 'ru']);

    [, $ukTerm] = pairedWord($user, 'uk', 'apple', ['uk' => 'яблуко', 'ru' => 'яблоко']);
    [, $ruTerm] = pairedWord($user, 'ru', 'bridge', ['ru' => 'мост', 'uk' => 'міст']);

    $shipped = syncedTranslations($this, $token);

    // Mixed pairs are legal (DECISIONS п. 128, 143) and each side keeps its own language — this is
    // the case one scalar per batch could not express at all.
    expect($shipped[$ukTerm])->toBe('яблуко')
        ->and($shipped[$ruTerm])->toBe('мост');
});

it('builds a session card in the pair language of the collection it was scoped to', function () {
    [$user, $token] = learner();
    $user->profile()->update(['native_language' => 'ru']);

    [$collectionId, $termId] = pairedWord($user, 'uk', 'apple', ['uk' => 'яблуко', 'ru' => 'яблоко']);
    // A recognition card needs somebody to be wrong beside it, so the deck gets two more words.
    addWordTo($collectionId, $user->id, 'bridge', 'міст');
    addWordTo($collectionId, $user->id, 'window', 'вікно');

    $cards = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/study/sessions', ['collection_id' => $collectionId])
        ->assertOk()
        ->json('data.cards');

    // Which rung the term is dealt at decides whether the Ukrainian sits in the prompt (term →
    // translation) or among the options (translation → term), so the assertion is about the CARD,
    // not about one field of it: the Ukrainian is on it and the Russian is nowhere near it.
    $onCards = [];
    foreach ($cards as $card) {
        if ($card['term_id'] === $termId) {
            $onCards[] = $card['prompt'];
            $onCards = [...$onCards, ...($card['options'] ?? [])];
        }
    }

    expect($onCards)->not->toBeEmpty()
        ->and($onCards)->toContain('яблуко')
        ->and($onCards)->not->toContain('яблоко');
});

it('shows the collection screen in the collection pair language', function () {
    [$user, $token] = learner();
    $user->profile()->update(['native_language' => 'ru']);

    [$collectionId, $termId] = pairedWord($user, 'uk', 'apple', ['uk' => 'яблуко', 'ru' => 'яблоко']);

    $items = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/v1/collections/{$collectionId}")
        ->assertOk()
        ->json('data.items');

    $row = array_values(array_filter($items, static fn (array $i): bool => $i['term_id'] === $termId))[0];
    expect($row['translation'])->toBe('яблуко');
});

it('resolves the pair language per term, and the profile only when no folder is left', function () {
    [$user, $token] = learner();
    $user->profile()->update(['native_language' => 'ru']);
    $actor = UserId::fromString($user->id);

    [$ukCollection, $ukTerm] = pairedWord($user, 'uk', 'apple', ['uk' => 'яблуко', 'ru' => 'яблоко']);
    [, $ruTerm] = pairedWord($user, 'ru', 'bridge', ['ru' => 'мост', 'uk' => 'міст']);

    $resolver = app(CardLanguageResolver::class);
    $ids = [TermId::fromString($ukTerm), TermId::fromString($ruTerm)];

    $perTerm = $resolver->forTerms($actor, $ids);
    expect($perTerm->for($ukTerm))->toBe('uk')
        ->and($perTerm->for($ruTerm))->toBe('ru');

    // A scope collapses the batch to one pair — every card in a scoped session is that deck's.
    expect($resolver->forTerms($actor, $ids, $ukCollection)->for($ruTerm))->toBe('uk');

    // Deleting a folder never touches the pool (DECISIONS п. 102), so a word can outlive its folder
    // and stay due with no pair left to read. The profile is the only answer available — and the
    // ONLY place it still speaks (п. 142).
    $this->withHeader('Authorization', "Bearer {$token}")
        ->deleteJson("/api/v1/collections/{$ukCollection}")
        ->assertNoContent();

    expect($resolver->forTerms($actor, $ids)->for($ukTerm))->toBe('ru');
});
