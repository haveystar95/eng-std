<?php

declare(strict_types=1);

use App\Modules\Learning\Application\Port\EnabledModesWriter;
use App\Modules\Learning\Domain\Service\LearningLadder;
use App\Modules\Learning\Domain\ValueObject\EnabledModes;
use App\Modules\Learning\Domain\ValueObject\ExerciseMode;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/** Give the term the pinned example the late form asks the learner to read aloud. */
function speakingExample(string $termId, string $sentence, string $translation = 'Не могли бы вы нас сфотографировать?'): void
{
    seedExample([
        'term_id' => $termId,
        'sentence' => $sentence,
        'translation' => $translation,
        'source' => 'ai',
    ]);
}

/** Switch speaking on for this user only — it ships off, so every test has to opt in. */
function enableSpeakingFor(string $userId): void
{
    app(EnabledModesWriter::class)->setOverrideFor(
        UserId::fromString($userId),
        new EnabledModes([ExerciseMode::Speaking]),
    );
}

/**
 * Upload one speaking answer AT A GIVEN RUNG and return the grade the server derived for it.
 *
 * The rung is the point: it is what tells the server which of the mode's two forms the card was,
 * and therefore what the answer is compared against and how.
 */
function speakingAnswer(object $ctx, string $token, string $termId, string $response, ?int $ladderStep = null, int $latencyMs = 6000): ?string
{
    static $seq = 0;
    $reviewId = Ulid::generate();

    $review = [
        'id' => $reviewId,
        'term_id' => $termId,
        'exercise_mode' => 'speaking',
        'response' => $response,
        'answered_at' => now()->toIso8601String(),
        'client_seq' => ++$seq,
        'latency_ms' => $latencyMs,
    ];
    if ($ladderStep !== null) {
        $review['ladder_step'] = $ladderStep;
    }

    $ctx->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/reviews/batch', ['reviews' => [$review]])
        ->assertOk()
        ->assertJsonPath('data.accepted', 1);

    $grade = DB::table('reviews')->where('id', $reviewId)->value('grade');

    return $grade !== null ? (string) $grade : null;
}

// ── the release rule ─────────────────────────────────────────────────────────

it('ships switched off, with a row on the assembly rung', function () {
    expect(config('learning.enabled_modes'))->not->toContain('speaking');

    $row = DB::table('learning_mode_settings')->whereNull('user_id')->where('mode', 'speaking')->first();

    expect($row)->not->toBeNull()
        ->and((bool) $row->enabled)->toBeFalse()
        ->and((string) $row->min_acquisition)->toBe('graduated')
        ->and($row->min_learning_step)->toBeNull()
        // No reviews threshold: the trainer opens WITH the assembly rung. Its late form is a form,
        // not a second admission — which is exactly why there is one row here and not two.
        ->and($row->min_successful_reviews)->toBeNull();
});

it('sits AFTER every trainer that shipped before it, so switching it on renumbers nothing', function () {
    $rows = DB::table('learning_mode_settings')->whereNull('user_id')->orderBy('position')->pluck('mode')->all();
    $at = array_search('speaking', $rows, true);

    // Not «last», which is what this asserted while speaking WAS the newest trainer. Every trainer
    // added since goes on the end too — that is the rule — so «last» would make each new one break
    // this test and tempt someone into walking speaking back up the list, renumbering the rotation
    // of every word already partway through it. What the rule actually promises is the statement
    // below: nothing that existed before speaking sits after it.
    expect($rows)->not->toBeEmpty()->and($at)->not->toBeFalse();
    foreach (['multiple_choice', 'word_bank', 'typing', 'listening', 'cloze', 'scramble', 'dictation', 'pick_correct', 'intro'] as $older) {
        expect(array_search($older, $rows, true))->toBeLessThan($at, "{$older} shipped before speaking and must not sit after it");
    }
});

it('reaches the device through /sync as a matrix row, still switched off', function () {
    [, $token] = learner();
    $settings = sync($this, $token)['settings'];

    $speaking = collect($settings['mode_admission'])->firstWhere('mode', 'speaking');

    expect($speaking)->not->toBeNull()
        ->and($speaking['min_step'])->toBe(LearningLadder::STEP_ASSEMBLY)
        // The matrix says where it WOULD sit; the toggle list is what says nobody has it yet.
        ->and($settings['exercise_modes'])->not->toContain('speaking');
});

// ── the two forms ────────────────────────────────────────────────────────────

it('asks for the WORD off the ladder — the term is the answer, the translation the cue', function () {
    [$user, $token] = learner();
    $termId = seedWordFor($user, 'reservation', 'бронь');
    speakingExample($termId, 'I have a reservation for tonight.');
    enableSpeakingFor($user->id);

    // A practice session is off the ladder entirely (it carries no rung), which is the word form.
    $card = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/study/sessions', ['limit' => 5, 'practice' => true])
        ->assertOk()
        ->json('data.cards.0');

    expect($card['exercise_mode'])->toBe('speaking')
        ->and($card['answer'])->toBe('reservation')
        ->and($card['prompt'])->toBe('бронь')
        // The example rides along for the feedback, exactly as it does on a typing card — it is
        // not the task here.
        ->and($card['example'])->toBe('I have a reservation for tonight.')
        ->and($card['options'])->toBeNull()
        ->and($card['chips'])->toBeNull();
});

it('grades the word form against the term, and a wrong word as a miss', function () {
    [$user, $token] = learner();
    $termId = seedWordFor($user, 'reservation', 'бронь');
    speakingExample($termId, 'I have a reservation for tonight.');

    expect(speakingAnswer($this, $token, $termId, 'reservation', LearningLadder::STEP_ASSEMBLY))->toBe('good')
        ->and(speakingAnswer($this, $token, $termId, 'registration', LearningLadder::STEP_ASSEMBLY))->toBe('again');
});

it('grades the example form by COVERAGE — the reading the recogniser mangled still passes', function () {
    [$user, $token] = learner();
    $termId = seedWordFor($user, 'photo', 'фото');
    speakingExample($termId, 'Could you take a photo of us?');

    // The same transcript, at the two rungs. Late: the sentence is the key and near enough is
    // enough. Early: the key is the term, and a whole sentence is not the term.
    expect(speakingAnswer($this, $token, $termId, 'could you take photo of us', LearningLadder::STEP_DICTATION))->toBe('good')
        ->and(speakingAnswer($this, $token, $termId, 'could you take photo of us', LearningLadder::STEP_ASSEMBLY))->toBe('again');
});

it('grades a PHRASE TERM by coverage too — a term is not always a word', function () {
    [$user, $token] = learner();
    // The card is the WORD form (rung 3, no example asked for), and the word is a whole question.
    $termId = seedWordFor($user, 'Where do you see yourself in five years?', 'Кем вы видите себя через пять лет?');

    // What an on-device recogniser actually returns for a correct reading: «yourself» heard as
    // «yourself» but the article-ish middle mangled, and no question mark. Held to equality this was
    // `again` — a lapse for a room, on a card the learner answered (BUGFIX-2 Ч.3б, owner's phone).
    expect(speakingAnswer($this, $token, $termId, 'where do you see yourself in 5 years', LearningLadder::STEP_ASSEMBLY))
        ->toBe('good')
        // …and it is coverage, not «anything goes»: half the question is still half the question.
        ->and(speakingAnswer($this, $token, $termId, 'where do you', LearningLadder::STEP_ASSEMBLY))->toBe('again');
});

it('keeps a SHORT term on equality — coverage over one word would accept it inside anything', function () {
    [$user, $token] = learner();
    $termId = seedWordFor($user, 'reservation', 'бронь');

    // A one-word key compared by coverage would pass for any sentence containing the word, which is
    // not what «произнеси слово» asks. The normalising stages already forgive what a one-word
    // reading really loses (case, punctuation, a dropped trailing sibilant).
    expect(speakingAnswer($this, $token, $termId, 'the reservation is over there', LearningLadder::STEP_ASSEMBLY))
        ->toBe('again')
        ->and(speakingAnswer($this, $token, $termId, 'Reservation.', LearningLadder::STEP_ASSEMBLY))->toBe('good');
});

it('fails the example form when only half the sentence was read', function () {
    [$user, $token] = learner();
    $termId = seedWordFor($user, 'photo', 'фото');
    speakingExample($termId, 'Could you take a photo of us?');

    expect(speakingAnswer($this, $token, $termId, 'could you', LearningLadder::STEP_DICTATION))->toBe('again');
});

it('degrades to the word form when the term has no example to read', function () {
    [$user, $token] = learner();
    $termId = seedWordFor($user, 'reservation', 'бронь'); // no term_examples row

    // At the late rung the mode ASKS for the example, but the term has none — the fallback is the
    // word, not a card graded against a sentence that does not exist.
    expect(speakingAnswer($this, $token, $termId, 'reservation', LearningLadder::STEP_DICTATION))->toBe('good');
});

// ── «не помню», and the skip that never arrives ──────────────────────────────

it('records «не помню» as an honest lapse — one review row, graded again', function () {
    [$user, $token] = learner();
    $termId = seedWordFor($user, 'reservation', 'бронь');
    speakingExample($termId, 'I have a reservation for tonight.');

    // The client sends an empty response for «не помню». It is the ONLY thing this trainer ever
    // sends that is not a real utterance, and it must be graded, never 422'd.
    expect(speakingAnswer($this, $token, $termId, '', LearningLadder::STEP_ASSEMBLY))->toBe('again');
    expect(DB::table('reviews')->where('term_id', $termId)->where('exercise_mode', 'speaking')->count())->toBe(1);
});

it('never sees a channel skip at all — a skipped card leaves no trace in its own session', function () {
    [$user, $token] = learner();
    $spoken = seedWordFor($user, 'reservation', 'бронь');
    $skipped = seedWordFor($user, 'photo', 'фото');

    // The server half of the skip rule, and it is a rule about ABSENCE. A session where the
    // recogniser could not hear one card uploads the OTHER card and simply omits this one — there
    // is no «skipped» verdict on the wire, because a microphone that failed must never be recorded
    // as a memory that failed. Asserted here so that a future «send it as a lapse anyway» has to
    // break a test rather than quietly cost the learner an interval.
    expect(speakingAnswer($this, $token, $spoken, 'reservation', LearningLadder::STEP_ASSEMBLY))->toBe('good');

    expect(DB::table('reviews')->where('term_id', $skipped)->count())->toBe(0)
        // Its pair row is the enrolment's and is untouched: never introduced, never answered.
        ->and(DB::table('user_term_progress')->where('term_id', $skipped)->where('acquisition', 'new')->where('reps', 0)->count())->toBe(1)
        // …while the card that DID produce an utterance went all the way through.
        ->and(DB::table('reviews')->where('term_id', $spoken)->count())->toBe(1);
});

it('caps at good however fast the answer came back', function () {
    [$user, $token] = learner();
    $termId = seedWordFor($user, 'reservation', 'бронь');

    // Fast, clean, unhinted — typing would give this `easy`. Speaking must not: the clock it would
    // be reading includes the recogniser's own settling time.
    expect(speakingAnswer($this, $token, $termId, 'reservation', LearningLadder::STEP_ASSEMBLY, latencyMs: 300))->toBe('good');
});
