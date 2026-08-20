<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Eloquent\Profile;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

// learner() / seedCollectionWith() / addWordTo() / answerTimes() live in tests/Pest.php.

/**
 * «ТРЕНИРОВКА ПО ТЕМЕ» OVER AN UNTRIAGED COLLECTION — the server's half.
 *
 * The owner's scenario: «зашёл в кафе, открыл тему, прошёл маленькую тренировку без разбора
 * коллекции». Free practice used to draw from the POOL alone, so a collection nobody had swiped
 * through produced an empty session and the button was a dead end.
 *
 * The device builds its own practice sessions offline, so this behaviour exists twice and drifts
 * silently — the phone would simply deal a card the server would not have. The client's half is
 * `mobile/test/data/practice/catalogue_practice_test.dart`.
 *
 * What is pinned here:
 *
 *   * a collection-scoped practice session drills the whole topic, untriaged words included;
 *   * a word outside the pool is dealt only what the matrix opens at
 *     `LearningLadder::STEP_UNENROLLED_PRACTICE` — never typing, listening or dictation;
 *   * a word IN the pool keeps its own trainers in the very same session;
 *   * the pool leads the session and the catalogue fills the tail;
 *   * and none of it moves progress: no enrolment, no exposure, no schedule.
 */

/** The trainers a word nobody has studied must never be asked in. */
const WITHHELD_FROM_CATALOGUE = ['typing', 'listening', 'dictation'];

it('drills an untriaged collection — the topic, not the queue', function () {
    [$user, $token] = learner();
    // daily_goal 0 → a study session introduces nothing, so anything dealt below is practice's doing.
    Profile::create(['user_id' => $user->id, 'daily_goal' => 0]);
    [$col] = seedCollectionWith($user, 'apple', 'яблоко', enroll: false);
    addWordTo($col, $user->id, 'bank', 'банк', enroll: false);
    addWordTo($col, $user->id, 'ledger', 'гроссбух', enroll: false);

    $cards = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/study/sessions', ['collection_id' => $col, 'practice' => true])
        ->assertOk()
        ->json('data.cards');

    expect(array_column($cards, 'answer'))->toEqualCanonicalizing(['apple', 'bank', 'ledger']);
});

it('asks a catalogue word only what the assembly rung opens', function () {
    [$user, $token] = learner();
    [$col] = seedCollectionWith($user, 'apple', 'яблоко', enroll: false);
    addWordTo($col, $user->id, 'bank', 'банк', enroll: false);

    // Several sessions, because the mode is a round-robin over the applicable set: one draw proves
    // little, and «typing never appeared» must not pass by luck.
    for ($i = 0; $i < 5; $i++) {
        $cards = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/study/sessions', ['collection_id' => $col, 'practice' => true])
            ->assertOk()
            ->json('data.cards');

        foreach ($cards as $card) {
            expect(WITHHELD_FROM_CATALOGUE)->not->toContain($card['exercise_mode']);
            // …and it carries no rung: practice is off the ladder entirely, catalogue or not.
            expect($card['ladder_step'])->toBeNull();
        }
    }
});

it('keeps a POOL word its own trainers in the very same session', function () {
    [$user, $token] = learner();
    [$col, $apple] = seedCollectionWith($user, 'apple', 'яблоко');
    addWordTo($col, $user->id, 'pear', 'груша'); // a neighbour, so a choice card can be built (QA-15)
    // …and a word of the same topic nobody has taken into study.
    $ledger = addWordTo($col, $user->id, 'ledger', 'гроссбух', enroll: false);

    // Walk `apple` off the recognition rungs, so its rung is real and its fan is the full one.
    answerTimes($this, $token, $apple, 'apple', times: 3);

    $dealtToPool = [];
    for ($i = 0; $i < 6; $i++) {
        $cards = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/study/sessions', ['collection_id' => $col, 'practice' => true])
            ->assertOk()
            ->json('data.cards');

        foreach ($cards as $card) {
            if ($card['term_id'] === $ledger) {
                expect(WITHHELD_FROM_CATALOGUE)->not->toContain($card['exercise_mode']);

                continue;
            }
            $dealtToPool[$card['exercise_mode']] = true;
        }
    }

    // These terms have no example, so the trainers their data can furnish are multiple_choice,
    // typing and listening — the last two are exactly what the catalogue cap withholds, and at
    // least one of them must still reach a word being studied. That is the cap not leaking.
    //
    // «At least one» and not «both»: the round-robin walks the applicable set by card index, and a
    // two-word pool only ever occupies two of the three positions in it. What is being asserted is
    // that the pool half of the session is not capped, and one withheld trainer proves that.
    expect(array_intersect(array_keys($dealtToPool), WITHHELD_FROM_CATALOGUE))->not->toBeEmpty();
});

it('leads with the words being studied and fills the tail with the catalogue', function () {
    [$user, $token] = learner();
    [$col, $apple] = seedCollectionWith($user, 'apple', 'яблоко');
    $pear = addWordTo($col, $user->id, 'pear', 'груша');
    addWordTo($col, $user->id, 'ledger', 'гроссбух', enroll: false);
    addWordTo($col, $user->id, 'invoice', 'счёт', enroll: false);

    $pool = [$apple, $pear];

    for ($i = 0; $i < 5; $i++) {
        $cards = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/study/sessions', ['collection_id' => $col, 'practice' => true])
            ->assertOk()
            ->json('data.cards');

        $seenCatalogue = false;
        foreach ($cards as $card) {
            if (! in_array($card['term_id'], $pool, true)) {
                $seenCatalogue = true;

                continue;
            }
            expect($seenCatalogue)->toBeFalse('a pool word came after a catalogue one');
        }
    }
});

it('spends a session too small for the topic on the pool first', function () {
    [$user, $token] = learner();
    [$col, $apple] = seedCollectionWith($user, 'apple', 'яблоко');
    addWordTo($col, $user->id, 'ledger', 'гроссбух', enroll: false);
    addWordTo($col, $user->id, 'invoice', 'счёт', enroll: false);

    $cards = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/study/sessions', ['collection_id' => $col, 'practice' => true, 'limit' => 1])
        ->assertOk()
        ->json('data.cards');

    expect(array_column($cards, 'term_id'))->toBe([$apple]);
});

it('moves nothing: answering a catalogue word leaves it in the catalogue', function () {
    [$user, $token] = learner();
    Profile::create(['user_id' => $user->id, 'daily_goal' => 5]);
    [$col] = seedCollectionWith($user, 'apple', 'яблоко', enroll: false);
    addWordTo($col, $user->id, 'bank', 'банк', enroll: false);

    $session = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/study/sessions', ['collection_id' => $col, 'practice' => true])
        ->assertOk()
        ->json('data');

    $card = $session['cards'][0];
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/reviews/batch', ['reviews' => [[
            'id' => Ulid::generate(),
            'term_id' => $card['term_id'],
            'exercise_mode' => $card['exercise_mode'],
            'response' => $card['answer'],
            'answered_at' => now()->toIso8601String(),
            'session_id' => $session['session_id'],
            'is_practice' => true,
            'client_seq' => 1,
        ]]])
        ->assertOk();

    // The answer IS in the log — practice keeps the streak…
    $this->assertDatabaseHas('reviews', ['term_id' => $card['term_id'], 'is_practice' => true]);
    // …and nothing else happened. Not enrolled, never introduced, never scheduled.
    expect(DB::table('user_term_progress')->whereNotNull('enrolled_at')->count())->toBe(0)
        ->and(DB::table('term_exposures')->count())->toBe(0);

    // …so a STUDY session over the same collection is still empty: the pool is still empty.
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/study/sessions', ['collection_id' => $col])
        ->assertOk()
        ->assertJsonPath('data.cards', []);
});

it('leaves the UNSCOPED free practice reading the pool alone', function () {
    [$user, $token] = learner();
    [$col] = seedCollectionWith($user, 'apple', 'яблоко', enroll: false);
    addWordTo($col, $user->id, 'bank', 'банк', enroll: false);

    // No collection was pointed at, so there is no topic to drill — only the queue, which is empty.
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/study/sessions', ['practice' => true])
        ->assertOk()
        ->assertJsonPath('data.cards', []);
});
