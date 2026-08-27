<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Eloquent\Profile;
use App\Modules\Learning\Application\Port\EnabledModesWriter;
use App\Modules\Learning\Domain\ValueObject\EnabledModes;
use App\Modules\Learning\Domain\ValueObject\ExerciseMode;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use App\Modules\Shared\Domain\ValueObject\UserId;
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
 *   * a word outside the pool is dealt only the RECEPTIVE CORNER
 *     ({@see \App\Modules\Learning\Domain\ValueObject\ModeAdmission::onlyPracticeCorner()}) — never
 *     «напиши по памяти» and never dictation;
 *   * a word IN the pool keeps its own trainers in the very same session;
 *   * the pool leads the session and the catalogue fills the tail;
 *   * and none of it moves progress: no enrolment, no exposure, no schedule.
 */

/**
 * The trainers a word nobody has studied must never be asked in — the two that ask it to be written
 * out of memory. «Свободная практика ступени 0 = рецептивные режимы; продуктивные (письмо по памяти,
 * диктант) открываются лестницей» (BUGFIX-2 Ч.2б).
 *
 * `listening` used to be on this list and is not any more: writing down a word the phone has just
 * said, as many times as asked, is RECEPTION — the sound is the question and it is on screen for as
 * long as the learner wants it.
 */
const WITHHELD_FROM_CATALOGUE = ['typing', 'dictation'];

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

    // ONE trainer switched on, and it is a withheld one. That makes the claim deterministic instead
    // of leaving it to the round-robin's seed: whatever card index each word lands on, the pool word
    // can only be dealt `typing` and the catalogue word can only be dealt the floor.
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

    // The word being studied gets the trainer it has earned…
    expect($byTerm[$apple])->toBe('typing')
        // …and the catalogue word, in the very same session, does not: `typing` is outside the
        // receptive corner, the corner comes out empty, and the floor deals the one trainer that
        // fits every term. That is the cap holding without capping the pool.
        ->and($byTerm[$ledger])->toBe('multiple_choice');
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

    // The one slot went to the POOL word — every card in the session is about it. Not «exactly one
    // card»: a session whose pool comes out at a single term FANS that term across its trainers
    // («Тренировать слово» is the same rule), so what is pinned here is WHOSE cards these are.
    expect(array_values(array_unique(array_column($cards, 'term_id'))))->toBe([$apple]);
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
