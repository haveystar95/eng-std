<?php

declare(strict_types=1);

use App\Modules\Learning\Application\Port\EnabledModesWriter;
use App\Modules\Learning\Domain\ValueObject\EnabledModes;
use App\Modules\Learning\Domain\ValueObject\ExerciseMode;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

// learner() / seedCollectionWith() / addWordTo() live in tests/Pest.php.

/**
 * A POOL WORD AT RUNG 0 IS DRILLED — AT THE EASY CORNER, AND IT MOVES NOTHING.
 *
 * The owner's rule (BUG-2, 27.08.2026): «тренировка доступна всегда, потому что она не влияет на
 * прогресс; прогресс двигает только сессия». The client used to refuse a drill to an enrolled word
 * that had not been met yet, so «Тренировать это слово» was a grey button on exactly the words the
 * learner had just decided to learn — and deciding to learn a word made it LESS practisable than
 * leaving it in the catalogue.
 *
 * The refusal was client-only; what the SERVER had wrong is the other half: such a pair was dealt
 * the full switched-on set, so a word nobody had ever seen could be asked to be typed from memory.
 * Both sides now ask one question — «does this pair have a rung of its own?»
 * ({@see \App\Modules\Learning\Application\Service\StudyCardAssembler::drillsAtOwnRung()},
 * mirrored as `LadderPosition.drillsAtOwnRung`) — and a pair that has not earned one is dealt what
 * the matrix opens at `LearningLadder::STEP_UNENROLLED_PRACTICE`.
 *
 * The client's half is `mobile/test/data/practice/ladder_gate_test.dart`.
 */

/**
 * The trainers a word that has never been met must never be asked in: the two that ask it to be
 * written out of memory, plus `intro` (practice introduces nothing). Everything else is the
 * RECEPTIVE CORNER — «свободная практика ступени 0 = рецептивные режимы; продуктивные (письмо по
 * памяти, диктант) открываются лестницей» (BUGFIX-2 Ч.2б).
 */
const WITHHELD_FROM_RUNG_ZERO = ['typing', 'dictation', 'intro'];

it('drills a pool word that has never been met', function () {
    [$user, $token] = learner();
    // Enrolled by default and never answered — acquisition `new`, rung 0. The neighbour is there so
    // a choice card has a second option to offer (QA-15).
    [$col] = seedCollectionWith($user, 'apple', 'яблоко');
    addWordTo($col, $user->id, 'pear', 'груша');

    expect(DB::table('user_term_progress')->where('acquisition', 'new')->count())->toBe(2);

    $cards = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/study/sessions', ['practice' => true])
        ->assertOk()
        ->json('data.cards');

    expect($cards)->not->toBeEmpty();
});

it('asks it only what the easy corner of the matrix opens', function () {
    [$user, $token] = learner();
    [$col] = seedCollectionWith($user, 'apple', 'яблоко');
    addWordTo($col, $user->id, 'pear', 'груша');

    // Several draws: the mode is a round-robin over the applicable set, so one session proves
    // little and «typing never appeared» must not pass by luck.
    for ($i = 0; $i < 5; $i++) {
        $cards = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/study/sessions', ['collection_id' => $col, 'practice' => true])
            ->assertOk()
            ->json('data.cards');

        expect($cards)->not->toBeEmpty();
        foreach ($cards as $card) {
            expect(WITHHELD_FROM_RUNG_ZERO)->not->toContain($card['exercise_mode']);
            // …and it carries no rung: practice is off the ladder entirely.
            expect($card['ladder_step'])->toBeNull();
        }
    }
});

it('moves nothing: the rung after a practice answer is the rung before it', function () {
    [$user, $token] = learner();
    [$col, $apple] = seedCollectionWith($user, 'apple', 'яблоко');
    addWordTo($col, $user->id, 'pear', 'груша');

    $before = DB::table('user_term_progress')->where('term_id', $apple)->first();

    $session = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/study/sessions', ['collection_id' => $col, 'practice' => true])
        ->assertOk()
        ->json('data');

    $card = collect($session['cards'])->firstWhere('term_id', $apple);
    expect($card)->not->toBeNull();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/reviews/batch', ['reviews' => [[
            'id' => Ulid::generate(),
            'term_id' => $apple,
            'exercise_mode' => $card['exercise_mode'],
            'response' => $card['answer'],
            'answered_at' => now()->toIso8601String(),
            'session_id' => $session['session_id'],
            'is_practice' => true,
            'client_seq' => 1,
        ]]])
        ->assertOk();

    // The answer IS in the log — a drill is activity, and the streak counts it…
    $this->assertDatabaseHas('reviews', ['term_id' => $apple, 'is_practice' => true]);

    // …and the ladder did not move an inch. Not the rung, not the counter, not the schedule — and
    // no exposure, because a drill never introduces.
    $after = DB::table('user_term_progress')->where('term_id', $apple)->first();
    expect($after->acquisition)->toBe($before->acquisition)
        ->and($after->acquisition)->toBe('new')
        ->and($after->learning_step)->toBe($before->learning_step)
        ->and($after->successful_reviews)->toBe($before->successful_reviews)
        ->and($after->due_at)->toBe($before->due_at)
        ->and(DB::table('term_exposures')->count())->toBe(0);
});

it('leaves a word that HAS a rung its own trainers', function () {
    [$user, $token] = learner();
    [$col, $apple] = seedCollectionWith($user, 'apple', 'яблоко');
    addWordTo($col, $user->id, 'pear', 'груша');

    // Walk `apple` off the recognition rungs, so its rung is real and its fan is the full one.
    answerTimes($this, $token, $apple, 'apple', times: 3);

    // ONE trainer switched on, and it is a withheld one — so the claim does not depend on where the
    // round-robin's seed happens to land. `pear` is still at rung 0 and rides along as the control.
    app(EnabledModesWriter::class)->setOverrideFor(
        UserId::fromString($user->id),
        new EnabledModes([ExerciseMode::Typing]),
    );

    $byTerm = [];
    foreach ($this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/study/sessions', ['collection_id' => $col, 'practice' => true])
        ->assertOk()
        ->json('data.cards') as $card) {
        $byTerm[$card['term_id']] = $card['exercise_mode'];
    }

    // The word that HAS a rung is dealt what it has earned — the cap is not leaking onto it (QA-26)…
    expect($byTerm[$apple])->toBe('typing')
        // …while the rung-0 word beside it falls to the floor, because `typing` is outside the
        // receptive corner and nothing else is switched on.
        ->and(array_values(array_diff_key($byTerm, [$apple => true])))->each->toBe('multiple_choice');
});
