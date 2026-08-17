<?php

declare(strict_types=1);

use App\Modules\Collections\Application\Command\AddWordToCollection;
use App\Modules\Collections\Application\Command\AddWordToCollectionHandler;
use App\Modules\Collections\Application\Command\CreateCustomCollection;
use App\Modules\Collections\Application\Command\CreateCustomCollectionHandler;
use App\Modules\Learning\Domain\ValueObject\Acquisition;
use App\Modules\Learning\Domain\ValueObject\LearningState;
use App\Modules\Shared\Domain\ValueObject\CollectionId;
use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * QA-14: «Тренировать слово» must run the word through the TRAINERS, not deal one card and end.
 *
 * The round-robin was already showing every applicable mode across a session — but it walks the
 * circle by CARD INDEX, so a pool of one word has one point on the circle and the session read
 * «1 of 1». The button's promise and the behaviour only ever agreed for many-word decks.
 *
 * The pool size is the rule on both runtimes (the device builds its own practice sessions), so
 * nothing about it travels on the wire.
 */

/** A graduated, well-reviewed pair: high enough on the ladder that the matrix admits everything. */
function graduate(string $userId, string $termId, int $reps = 12): void
{
    DB::table('user_term_progress')->updateOrInsert(
        ['user_id' => $userId, 'term_id' => $termId],
        [
            'state' => LearningState::Review->value,
            'acquisition' => Acquisition::Graduated->value,
            'learning_step' => 0,
            'reps' => $reps,
            'lapses' => 0,
            'ease_factor' => 2.5,
            'interval_days' => 10,
            'due_at' => now()->addDays(3),
            'created_at' => now(),
            'updated_at' => now(),
        ],
    );
}

/** A collection holding one rich term (example + translation), ready for every mode's gate. */
function soloDeck(object $user, string $text = 'withdraw cash'): array
{
    $actor = UserId::fromString($user->id);
    $collectionId = app(CreateCustomCollectionHandler::class)(new CreateCustomCollection(
        $actor, 'Solo', new LanguageCode('ru'), new LanguageCode('en'),
    ))->value;

    $termId = app(AddWordToCollectionHandler::class)(new AddWordToCollection(
        CollectionId::fromString($collectionId), $actor, $text, 'снять наличные',
    ))->value;

    DB::table('term_examples')->insert([
        'id' => (string) \App\Modules\Shared\Domain\ValueObject\Ulid::generate(),
        'term_id' => $termId,
        'sentence' => 'I need to withdraw cash from the machine.',
        'sentence_translation' => 'Мне нужно снять наличные в банкомате.',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return [$collectionId, $termId];
}

it('gives a ONE-TERM practice session a card per applicable mode', function () {
    [$user, $token] = learner();
    [$collectionId, $termId] = soloDeck($user);
    graduate($user->id, $termId);

    $cards = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/study/sessions', ['collection_id' => $collectionId, 'practice' => true])
        ->assertOk()
        ->json('data.cards');

    expect(count($cards))->toBeGreaterThan(1, 'this is the «1 of 1» the acceptance saw');
    // Every card is the same word, and no trainer is dealt twice — a fan, not a repeat.
    expect(array_unique(array_column($cards, 'term_id')))->toBe([$termId]);
    $modes = array_column($cards, 'exercise_mode');
    expect($modes)->toBe(array_values(array_unique($modes)));
    expect($modes)->not->toContain('intro', 'practice introduces nothing');
});

it('deals the fan in the matrix own order, not in rotation order', function () {
    [$user, $token] = learner();
    [$collectionId, $termId] = soloDeck($user);
    graduate($user->id, $termId);

    $cards = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/study/sessions', ['collection_id' => $collectionId, 'practice' => true])
        ->assertOk()
        ->json('data.cards');

    // `learning_mode_settings.position` is the product's own order; the enabled set is read in it
    // and the fan walks it unchanged.
    $enabled = DB::table('learning_mode_settings')
        ->whereNull('user_id')
        ->where('enabled', true)
        ->orderBy('position')->orderBy('mode')
        ->pluck('mode')
        ->all();

    $dealt = array_column($cards, 'exercise_mode');
    expect($dealt)->toBe(array_values(array_filter($enabled, static fn (string $m): bool => in_array($m, $dealt, true))));
});

it('never fans a MANY-word practice session — one card per term, as before', function () {
    [$user, $token] = learner();
    $actor = UserId::fromString($user->id);
    $collectionId = app(CreateCustomCollectionHandler::class)(new CreateCustomCollection(
        $actor, 'Many', new LanguageCode('ru'), new LanguageCode('en'),
    ))->value;

    $ids = [];
    foreach (['apple' => 'яблоко', 'bank' => 'банк', 'towel' => 'полотенце'] as $text => $translation) {
        $ids[] = $id = app(AddWordToCollectionHandler::class)(new AddWordToCollection(
            CollectionId::fromString($collectionId), $actor, $text, $translation,
        ))->value;
        graduate($user->id, $id);
    }

    $cards = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/study/sessions', ['collection_id' => $collectionId, 'practice' => true])
        ->assertOk()
        ->json('data.cards');

    expect($cards)->toHaveCount(count($ids));
    expect(array_column($cards, 'term_id'))->toEqualCanonicalizing($ids);
});

it('still deals ONE card when the pair is admitted to nothing yet', function () {
    [$user, $token] = learner();
    [$collectionId, $termId] = soloDeck($user);
    // Rung 1: the matrix admits multiple_choice and nothing else, so the fan is a fan of one. The
    // point of the assertion is that «few modes apply» never becomes «no session».
    DB::table('user_term_progress')->updateOrInsert(
        ['user_id' => $user->id, 'term_id' => $termId],
        [
            'state' => LearningState::Learning->value,
            'acquisition' => Acquisition::Learning->value,
            'learning_step' => 1,
            'reps' => 0, 'lapses' => 0, 'ease_factor' => 2.5, 'interval_days' => 0, 'due_at' => null,
            'created_at' => now(), 'updated_at' => now(),
        ],
    );

    $cards = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/study/sessions', ['collection_id' => $collectionId, 'practice' => true])
        ->assertOk()
        ->json('data.cards');

    expect($cards)->toHaveCount(1);
    expect($cards[0]['exercise_mode'])->toBe('multiple_choice');
    expect($cards[0]['ladder_step'])->toBeNull('practice is off the ladder, whatever rung the pair is on');
});
