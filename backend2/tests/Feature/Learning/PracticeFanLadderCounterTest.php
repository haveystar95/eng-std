<?php

declare(strict_types=1);

use App\Modules\Collections\Application\Command\AddWordToCollection;
use App\Modules\Collections\Application\Command\AddWordToCollectionHandler;
use App\Modules\Collections\Application\Command\CreateCustomCollection;
use App\Modules\Collections\Application\Command\CreateCustomCollectionHandler;
use App\Modules\Learning\Domain\Service\LearningLadder;
use App\Modules\Learning\Domain\ValueObject\Acquisition;
use App\Modules\Learning\Domain\ValueObject\LearningState;
use App\Modules\Shared\Domain\ValueObject\CollectionId;
use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * QA-18, the SECOND site: the free-practice fan read the rung off `reps` as well.
 *
 * The counter was split in two on 2026-08-18 for a live reason — `antipyretic` sat on the dictation
 * rung off four misses and two hits, because `reps` counts every branch the scheduler takes and
 * `again` in the learning state re-queues the pair inside the same session. The fix gave the ladder
 * its own column and pointed the SESSION at it. `StudyCardAssembler::practiceModesFor()` kept
 * passing `reps`, so «Тренировать это слово» went on admitting the hardest card in the app to a word
 * whose only achievement was being forgotten six times.
 *
 * A pure-unit test of {@see LearningLadder} would not have caught it: the ladder was always right,
 * and the argument handed to it was wrong. So this is asked of the endpoint, in the same units the
 * learner meets — «I tapped „Тренировать" and got a dictation».
 */

/** A rich, single-word deck: an example and its translation, so every trainer's material gate passes. */
function counterDeck(object $user): array
{
    $actor = UserId::fromString($user->id);
    $collectionId = app(CreateCustomCollectionHandler::class)(new CreateCustomCollection(
        $actor, 'Аптека', new LanguageCode('ru'), new LanguageCode('en'),
    ))->value;

    $termId = app(AddWordToCollectionHandler::class)(new AddWordToCollection(
        CollectionId::fromString($collectionId), $actor, 'antipyretic', 'жаропонижающее',
    ))->value;

    seedExample([
        'term_id' => $termId,
        'sentence' => 'The pharmacist recommended an antipyretic for the fever.',
        'translation' => 'Фармацевт посоветовал жаропонижающее от температуры.',
    ]);

    return [$collectionId, $termId];
}

/**
 * `antipyretic`'s own row from the owner's database: shown eight times, recalled twice.
 *
 * `reps` and `successful_reviews` are deliberately far apart — that gap IS the bug, and a fixture
 * where they agree cannot fail.
 */
function shownOftenRecalledRarely(string $userId, string $termId): void
{
    DB::table('user_term_progress')->updateOrInsert(
        ['user_id' => $userId, 'term_id' => $termId],
        [
            'state' => LearningState::Review->value,
            'acquisition' => Acquisition::Graduated->value,
            'learning_step' => 0,
            'reps' => LearningLadder::DICTATION_MIN_SUCCESSES,      // enough to fake the top rung…
            'successful_reviews' => 2,                              // …and not enough to earn it
            'lapses' => 4,
            'ease_factor' => 2.5,
            'interval_days' => 10,
            'due_at' => now()->addDays(3),
            'enrolled_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ],
    );
}

it('does not deal a dictation to a word that was shown often and recalled rarely', function () {
    [$user, $token] = learner();
    [$collectionId, $termId] = counterDeck($user);
    shownOftenRecalledRarely($user->id, $termId);

    $modes = array_column(
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/study/sessions', ['collection_id' => $collectionId, 'practice' => true])
            ->assertOk()
            ->json('data.cards'),
        'exercise_mode',
    );

    // Two successes stand at ASSEMBLY. Typing is four and dictation is six — neither is earned, and
    // neither may be dealt however many times the scheduler has been called about this pair.
    //
    // `typing` is the load-bearing half of this assertion: `dictation` is not in the shipped enabled
    // set at all, so a test resting on its absence alone would pass on a build where the rung was
    // read off `reps` again. The control below shows `typing` DOES arrive when the successes do.
    expect($modes)->not->toBeEmpty()
        ->and($modes)->not->toContain('typing')
        ->and($modes)->not->toContain('dictation');
});

it('deals the typed rung once the successes are actually there', function () {
    [$user, $token] = learner();
    [$collectionId, $termId] = counterDeck($user);
    shownOftenRecalledRarely($user->id, $termId);

    // The one column that decides it. Nothing else about the pair changes — which is the point:
    // the rung follows the successes and nothing else.
    DB::table('user_term_progress')->where('user_id', $user->id)->where('term_id', $termId)
        ->update(['successful_reviews' => LearningLadder::TYPING_MIN_SUCCESSES]);

    $modes = array_column(
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/study/sessions', ['collection_id' => $collectionId, 'practice' => true])
            ->assertOk()
            ->json('data.cards'),
        'exercise_mode',
    );

    expect($modes)->toContain('typing');
});
