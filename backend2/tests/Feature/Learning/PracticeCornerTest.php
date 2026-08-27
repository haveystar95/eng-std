<?php

declare(strict_types=1);

use App\Modules\Learning\Domain\ValueObject\ExerciseMode;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Vocabulary\Application\Port\TermDescriptionWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * THE RUNG-0 CORNER OF FREE PRACTICE — the canon, stated by the owner and the architect
 * (BUGFIX-2 Ч.2б):
 *
 *   «Свободная практика ступени 0 = рецептивные режимы; продуктивные (письмо по памяти, диктант)
 *    открываются лестницей.»
 *
 * So a word with no rung of its own — outside the pool, or in it and still at rung 0 — is dealt
 * узнавание/выбор, сборка word_bank, произнеси, аудио «услышал→напиши» and description_match при
 * наличии описания, and is dealt neither `typing` nor `dictation`. A word standing on a rung of its
 * own gets the full fan, bounded only by its material and its language.
 *
 * Two things this pins that the old rule got wrong, both from the owner's phone:
 *
 *  * `listening` was withheld from the corner, because the ADMISSION MATRIX opens it at the typed
 *    rung — where it belongs in a planned session, right after typing. Free practice is not a
 *    planned session: it schedules nothing, so «услышал → напиши» asks no more of a first meeting
 *    than tapping does, and the corner now draws its own line ({@see ModeAdmission::onlyPracticeCorner()}).
 *  * `word_bank` was withheld from every SINGLE-WORD term, so the assembly trainer was missing from
 *    the fan of exactly the words the owner drills most. A single word assembles from its letters
 *    now (Ч.2б D2) — the branch ChipShuffler has always had and nothing could reach.
 *
 * The client's half of this is `mobile/test/data/practice/practice_corner_test.dart`.
 */

/** Switch every trainer on globally — what the owner does from «Тренажёры» in the admin panel. */
function cornerEnableEveryTrainer(): void
{
    DB::table('learning_mode_settings')->whereNull('user_id')->update(['enabled' => true]);
}

/**
 * The FAN a one-term practice pool deals, as modes.
 *
 * Unscoped practice reads the pool alone, so seeding exactly one enrolled term makes the session a
 * fan across that term's applicable trainers — the whole set at once, rather than one round-robin
 * draw whose outcome depends on a hash.
 *
 * @return list<string>
 */
function cornerFan(object $ctx, string $token): array
{
    return array_values(array_unique(array_map(
        static fn (array $c): string => (string) $c['exercise_mode'],
        $ctx->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/study/sessions', ['practice' => true])
            ->assertOk()
            ->json('data.cards'),
    )));
}

/** One enrolled word with the full set of material, plus somewhere for wrong options to come from. */
function cornerSeedWord(object $user): string
{
    [, $termId] = seedCollectionWith($user, 'invoice', 'счёт');
    seedExample([
        'term_id' => $termId,
        'sentence' => 'Could you send me the invoice by email?',
        'translation' => 'Не пришлёшь мне счёт по почте?',
        'source' => 'ai',
    ]);
    app(TermDescriptionWriter::class)->ensure(
        TermId::fromString($termId),
        'en',
        'A document asking for payment for goods or services.',
    );
    // A neighbour the distractor reader can top up from — outside the pool, so the fan above stays
    // a one-term fan.
    seedCollectionWith($user, 'ledger', 'книга учёта', enroll: false);

    return $termId;
}

it('deals the receptive corner to a word at rung 0 — audio and description_match included', function () {
    cornerEnableEveryTrainer();
    [$user, $token] = learner();
    cornerSeedWord($user);

    $fan = cornerFan($this, $token);

    expect($fan)
        ->toContain(ExerciseMode::MultipleChoice->value)
        // Assembly, on a SINGLE word: the letter chips (Ч.2б D2).
        ->toContain(ExerciseMode::WordBank->value)
        ->toContain(ExerciseMode::Cloze->value)
        ->toContain(ExerciseMode::Speaking->value)
        // The two the canon adds back to this corner.
        ->toContain(ExerciseMode::Listening->value)
        ->toContain(ExerciseMode::DescriptionMatch->value)
        // …and the two it keeps out: writing a word out of memory, and a whole sentence by ear.
        ->not->toContain(ExerciseMode::Typing->value)
        ->not->toContain(ExerciseMode::Dictation->value)
        // Practice introduces nothing, rung 0 or not.
        ->not->toContain(ExerciseMode::Intro->value);
});

it('opens the productive trainers once the word stands on a rung of its own', function () {
    cornerEnableEveryTrainer();
    [$user, $token] = learner();
    $termId = cornerSeedWord($user);

    // Up to the dictation rung: the two recognition steps do not count towards it (they schedule
    // nothing), so it takes those two plus DICTATION_MIN_SUCCESSES answers to get there.
    answerTimes($this, $token, $termId, 'invoice', times: 8);

    $fan = cornerFan($this, $token);

    expect($fan)
        ->toContain(ExerciseMode::Typing->value)
        ->toContain(ExerciseMode::Dictation->value)
        // …and it did not LOSE the corner on the way up: the full fan is the corner plus these two,
        // bounded by material and language, never a different set.
        ->toContain(ExerciseMode::Listening->value)
        ->toContain(ExerciseMode::WordBank->value)
        ->toContain(ExerciseMode::DescriptionMatch->value);
});

it('assembles a single word from its letters, and a phrase from its words', function () {
    cornerEnableEveryTrainer();
    [$user, $token] = learner();
    cornerSeedWord($user);

    $cards = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/study/sessions', ['practice' => true])
        ->assertOk()
        ->json('data.cards');

    $wordBank = collect($cards)->firstWhere('exercise_mode', ExerciseMode::WordBank->value);

    expect($wordBank)->not->toBeNull()
        // Seven letters of «invoice», shuffled — not one chip holding the whole word.
        ->and($wordBank['chips'])->toHaveCount(7)
        ->and(collect($wordBank['chips'])->sort()->values()->all())
        ->toBe(collect(str_split('invoice'))->sort()->values()->all());
});
