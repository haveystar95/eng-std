<?php

declare(strict_types=1);

use App\Modules\Collections\Application\Command\CreateCustomCollection;
use App\Modules\Collections\Application\Command\CreateCustomCollectionHandler;
use App\Modules\Learning\Application\Port\EnabledModesWriter;
use App\Modules\Learning\Domain\ValueObject\EnabledModes;
use App\Modules\Learning\Domain\ValueObject\ExerciseMode;
use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * The language gate as the learner meets it: the SAME content, the SAME switched-on trainer, and a
 * card that is dealt in English and refused in Polish (DECISIONS пп. 47, 130).
 *
 * `pick_correct` is the case because it is the one the capability matrix is unambiguous about: the
 * distractor taxonomy is «типичные ошибки русскоязычного в английском», and `article` is not a class
 * of error that exists in Polish at all.
 */
function langDeck(App\Modules\Identity\Infrastructure\Eloquent\User $user, string $studied): string
{
    return app(CreateCustomCollectionHandler::class)(new CreateCustomCollection(
        UserId::fromString($user->id), "deck-{$studied}", new LanguageCode('ru'), new LanguageCode($studied),
    ))->value;
}

/** A term with a pinned example, its gloss, and two span-distinct distractors — pick_correct-ready. */
function pickCorrectReady(string $termId, string $sentence, string $gloss): void
{
    $exampleId = Ulid::generate();
    seedExample([
        'id' => $exampleId,
        'term_id' => $termId,
        'sentence' => $sentence,
        'translation' => $gloss,
        'source' => 'ai',
    ]);

    foreach ([['tense', 'a', 'b'], ['preposition', 'c', 'd']] as $i => [$type, $span, $correction]) {
        DB::table('example_distractors')->insert([
            'id' => Ulid::generate(),
            'example_id' => $exampleId,
            'sentence' => $sentence . ' ' . $i,
            'error_type' => $type,
            'error_span' => $span,
            'correction' => $correction,
            'generator_version' => 'enrich-v1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

function onlyMode(string $userId, ExerciseMode ...$modes): void
{
    app(EnabledModesWriter::class)->setOverrideFor(UserId::fromString($userId), new EnabledModes($modes));
}

function firstCard(object $ctx, string $token, string $collectionId): ?array
{
    return $ctx->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/study/sessions', ['id' => Ulid::generate(), 'collection_id' => $collectionId])
        ->assertOk()
        ->json('data.cards.0');
}

it('deals pick_correct on an English card', function () {
    [$user, $token] = learner();
    $deck = langDeck($user, 'en');
    $termId = addWordTo($deck, $user->id, 'workstation', 'рабочее место');
    pickCorrectReady($termId, 'Your workstation is ready for you.', 'Ваше рабочее место готово.');
    onlyMode($user->id, ExerciseMode::PickCorrect);

    expect(firstCard($this, $token, $deck)['exercise_mode'])->toBe('pick_correct');
});

it('refuses pick_correct on a Polish card with identical content', function () {
    [$user, $token] = learner();
    $deck = langDeck($user, 'pl');
    $termId = addWordTo($deck, $user->id, 'stanowisko', 'рабочее место');
    pickCorrectReady($termId, 'Twoje stanowisko jest gotowe.', 'Ваше рабочее место готово.');
    // …and a neighbour, so the multiple_choice floor the card falls to is actually buildable (QA-15).
    addWordTo($deck, $user->id, 'okno', 'окно');
    onlyMode($user->id, ExerciseMode::PickCorrect);

    // The mode is on, the content is there, the pair is at the right rung — and the language closes
    // it. The card falls back rather than disappearing: a learner is never left with nothing.
    $card = firstCard($this, $token, $deck);
    expect($card)->not->toBeNull()
        ->and($card['exercise_mode'])->not->toBe('pick_correct');
});

it('leaves the trainers Polish CAN carry alone', function () {
    [$user, $token] = learner();
    $deck = langDeck($user, 'pl');
    $termId = addWordTo($deck, $user->id, 'stanowisko', 'рабочее место');
    pickCorrectReady($termId, 'Twoje stanowisko jest gotowe.', 'Ваше рабочее место готово.');
    onlyMode($user->id, ExerciseMode::Typing);

    expect(firstCard($this, $token, $deck)['exercise_mode'])->toBe('typing');
});

it('filters PER CARD, so one session can mix a Polish deck and an English one', function () {
    [$user, $token] = learner();
    $english = langDeck($user, 'en');
    $polish = langDeck($user, 'pl');
    $en = addWordTo($english, $user->id, 'workstation', 'рабочее место');
    $pl = addWordTo($polish, $user->id, 'stanowisko', 'стенд');
    pickCorrectReady($en, 'Your workstation is ready for you.', 'Ваше рабочее место готово.');
    pickCorrectReady($pl, 'Twoje stanowisko jest gotowe.', 'Ваш стенд готов.');
    onlyMode($user->id, ExerciseMode::PickCorrect, ExerciseMode::Typing);

    // Unscoped: the pool session, which is where pairs actually mix (DECISIONS пп. 128, 143).
    $cards = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/study/sessions', ['id' => Ulid::generate()])
        ->assertOk()
        ->json('data.cards');

    $modeOf = static function (array $cards, string $termId): array {
        return array_values(array_map(
            static fn (array $c): string => $c['exercise_mode'],
            array_filter($cards, static fn (array $c): bool => $c['term_id'] === $termId),
        ));
    };

    expect($modeOf($cards, $en))->toContain('pick_correct')
        ->and($modeOf($cards, $pl))->not->toContain('pick_correct')
        ->and($modeOf($cards, $pl))->not->toBeEmpty();
});
