<?php

declare(strict_types=1);

use App\Modules\Learning\Domain\Entity\TermProgress;
use App\Modules\Learning\Domain\Service\AnswerGrader;
use App\Modules\Learning\Domain\Service\ChipShuffler;
use App\Modules\Learning\Domain\Service\ExerciseSelector;
use App\Modules\Learning\Domain\Service\LearningLadder;
use App\Modules\Learning\Domain\Service\PlayabilityAssessor;
use App\Modules\Learning\Domain\Service\SentenceTokenizer;
use App\Modules\Learning\Domain\Service\SpokenCoverage;
use App\Modules\Learning\Domain\ValueObject\Answer;
use App\Modules\Learning\Domain\ValueObject\EnabledModes;
use App\Modules\Learning\Domain\ValueObject\ExerciseMode;
use App\Modules\Learning\Domain\ValueObject\ExpectedAnswer;
use App\Modules\Learning\Domain\ValueObject\Grade;
use App\Modules\Learning\Domain\ValueObject\LatencyBaseline;
use App\Modules\Learning\Domain\ValueObject\LearningState;
use App\Modules\Learning\Domain\ValueObject\MatchPolicy;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Shared\Domain\ValueObject\UserId;

beforeEach(function () {
    $this->assess = new PlayabilityAssessor(new ChipShuffler(), new SentenceTokenizer());
    $this->selector = new ExerciseSelector();
    $this->grader = new AnswerGrader();
    $this->coverage = new SpokenCoverage();
});

// ── which form the card asks for ─────────────────────────────────────────────

it('asks for the WORD from the assembly rung and for the EXAMPLE from the dictation rung', function () {
    expect(ExerciseMode::Speaking->gradesAgainstExample(LearningLadder::STEP_ASSEMBLY))->toBeFalse()
        ->and(ExerciseMode::Speaking->gradesAgainstExample(LearningLadder::STEP_TYPING))->toBeFalse()
        ->and(ExerciseMode::Speaking->gradesAgainstExample(LearningLadder::STEP_DICTATION))->toBeTrue()
        // Above the top rung the later form stays — a threshold, not an equality.
        ->and(ExerciseMode::Speaking->gradesAgainstExample(LearningLadder::STEP_DICTATION + 1))->toBeTrue();
});

it('asks for the word when the ladder does not apply — practice and a known verification', function () {
    // Null rung = off the ladder. The word form is the honest reading: practice drills the term,
    // and nothing off the ladder has EARNED the later form.
    expect(ExerciseMode::Speaking->gradesAgainstExample(null))->toBeFalse();
});

it('leaves every other mode\'s question untouched by the rung', function () {
    foreach ([LearningLadder::STEP_ASSEMBLY, LearningLadder::STEP_DICTATION, null] as $step) {
        expect(ExerciseMode::Typing->gradesAgainstExample($step))->toBeFalse()
            ->and(ExerciseMode::Scramble->gradesAgainstExample($step))->toBeTrue()
            ->and(ExerciseMode::Dictation->gradesAgainstExample($step))->toBeTrue()
            ->and(ExerciseMode::PickCorrect->gradesAgainstExample($step))->toBeTrue();
    }
});

// ── playability ──────────────────────────────────────────────────────────────

it('fits every term — a word with no example is still a word you can say', function () {
    expect($this->assess->assess('reservation', null, null)->supports(ExerciseMode::Speaking))->toBeTrue()
        ->and($this->assess->assess('reservation', '', null)->supports(ExerciseMode::Speaking))->toBeTrue()
        ->and($this->assess->assess('front desk', 'Ask at the front desk.', 'Спроси на ресепшене.')
            ->supports(ExerciseMode::Speaking))->toBeTrue();
});

// ── the ladder ───────────────────────────────────────────────────────────────

it('is a rung-3 trainer — the recognition rungs never meet it', function () {
    $enabled = new EnabledModes([ExerciseMode::MultipleChoice, ExerciseMode::Speaking]);
    $playable = $this->assess->assess('reservation', 'I have a reservation for tonight.', 'Перевод.');

    foreach ([LearningLadder::STEP_RECOGNITION_FORWARD, LearningLadder::STEP_RECOGNITION_REVERSE] as $step) {
        $progress = TermProgress::reconstitute(
            UserId::generate(), TermId::generate(), LearningState::Learning, 2.5, 0, null,
            reps: 0, lapses: 0, lastReviewedAt: null,
            acquisition: \App\Modules\Learning\Domain\ValueObject\Acquisition::Learning, learningStep: $step,
        );

        expect($this->selector->select($progress, $enabled, $playable, shippedMatrix()))
            ->not->toBe(ExerciseMode::Speaking);
    }
});

it('takes its turn in the review rotation, last of the rungs', function () {
    $enabled = new EnabledModes([ExerciseMode::Typing, ExerciseMode::Speaking]);
    $playable = $this->assess->assess('reservation', 'I have a reservation for tonight.', 'Перевод.');

    $review = fn (int $reps) => $this->selector->select(
        TermProgress::reconstitute(UserId::generate(), TermId::generate(), LearningState::Review, 2.5, 10, null, reps: $reps, lapses: 0, lastReviewedAt: null),
        $enabled,
        $playable,
        shippedMatrix(),
    );

    // At rung 4 typing has opened, so the rotation is those two in ladder order (speaking last).
    expect($review(4))->toBe(ExerciseMode::Typing)
        ->and($review(5))->toBe(ExerciseMode::Speaking);
});

it('is never dealt while it is switched off — the release rule, in one assertion', function () {
    $off = new EnabledModes([ExerciseMode::Typing, ExerciseMode::Listening]);
    $playable = $this->assess->assess('reservation', 'I have a reservation for tonight.', 'Перевод.');

    expect($playable->supports(ExerciseMode::Speaking))->toBeTrue();
    for ($reps = 0; $reps <= 12; $reps++) {
        expect($this->selector->select(
            TermProgress::reconstitute(UserId::generate(), TermId::generate(), LearningState::Review, 2.5, 10, null, reps: $reps, lapses: 0, lastReviewedAt: null),
            $off,
            $playable,
            shippedMatrix(),
        ))->not->toBe(ExerciseMode::Speaking);
    }
});

// ── grading ──────────────────────────────────────────────────────────────────

it('never awards easy — the speed it would be measuring is the recogniser\'s', function () {
    expect(ExerciseMode::Speaking->maxGrade())->toBe(Grade::Good)
        ->and(ExerciseMode::Speaking->isProduction())->toBeFalse();

    // Even a very fast, unhinted, correct answer stops at `good`.
    $grade = $this->grader->grade(
        new Answer('reservation', usedHint: false, latencyMs: 300),
        ExerciseMode::Speaking,
        new ExpectedAnswer(['reservation']),
        LatencyBaseline::insufficient(),
    );

    expect($grade)->toBe(Grade::Good);
});

it('forgives no typos — a recogniser returns words, not slipped keys', function () {
    expect(ExerciseMode::Speaking->forgivesTypos())->toBeFalse();

    // «bear» heard for «bare» is one character apart and is a DIFFERENT word. Typing would call
    // this `hard`; speaking must not, or the homophone the learner never said scores correct.
    $grade = $this->grader->grade(
        new Answer('bear', usedHint: false, latencyMs: 3000),
        ExerciseMode::Speaking,
        new ExpectedAnswer(['bare']),
        LatencyBaseline::insufficient(),
    );

    expect($grade)->toBe(Grade::Again);
});

it('grades the WORD form by the same canonicalisation as every other trainer', function () {
    $grade = fn (string $said) => $this->grader->grade(
        new Answer($said, usedHint: false, latencyMs: 4000),
        ExerciseMode::Speaking,
        new ExpectedAnswer(['the front desk']),
        LatencyBaseline::insufficient(),
    );

    // Case, punctuation and the leading article are the shared normaliser's business, not this
    // mode's — a recognised «Front desk.» is the same answer as «the front desk».
    expect($grade('Front desk.'))->toBe(Grade::Good)
        ->and($grade('the FRONT DESK'))->toBe(Grade::Good)
        ->and($grade('back desk'))->toBe(Grade::Again);
});

// ── coverage (the example form) ──────────────────────────────────────────────

it('passes a reading the recogniser mangled in its usual ways', function () {
    $expected = 'Could you take a photo of us?';

    // Dropped article, dropped punctuation, a swapped unstressed word — every one of these is a
    // learner who read the sentence correctly into a microphone in a real room.
    expect($this->coverage->covers('could you take a photo of us', $expected))->toBeTrue()
        ->and($this->coverage->covers('could you take photo of us', $expected))->toBeTrue()
        ->and($this->coverage->covers('could you take a photo of as', $expected))->toBeTrue();
});

it('fails a reading that stopped halfway', function () {
    expect($this->coverage->covers('could you take', 'Could you take a photo of us?'))->toBeFalse()
        ->and($this->coverage->covers('', 'Could you take a photo of us?'))->toBeFalse()
        ->and($this->coverage->covers('completely different words entirely', 'Could you take a photo of us?'))->toBeFalse();
});

it('counts by multiset — saying a repeated word once is not saying it twice', function () {
    // 3 of 4 words = 75%, above the bar; but the second «very» has to be found on its own.
    expect($this->coverage->ratio('it is very very cold', 'It is very very cold.'))->toBe(1.0)
        ->and($this->coverage->ratio('very', 'very very'))->toBe(0.5);
});

it('covers nothing when there is nothing expected — never a vacuous pass', function () {
    expect($this->coverage->ratio('anything at all', ''))->toBe(0.0)
        ->and($this->coverage->covers('anything at all', '   '))->toBeFalse();
});

it('routes the coverage key through the grader instead of the equality stages', function () {
    $sentence = 'Could you take a photo of us?';
    $grade = fn (string $said, MatchPolicy $policy) => $this->grader->grade(
        new Answer($said, usedHint: false, latencyMs: 6000),
        ExerciseMode::Speaking,
        new ExpectedAnswer([$sentence], isPhrase: true, policy: $policy),
        LatencyBaseline::insufficient(),
    );

    // The whole point, in one comparison: the SAME transcript is a lapse under equality and a
    // pass under coverage. Getting this backwards hands the scheduler a lapse for a noisy room.
    expect($grade('could you take photo of us', MatchPolicy::Coverage))->toBe(Grade::Good)
        ->and($grade('could you take photo of us', MatchPolicy::Exact))->toBe(Grade::Again);
});

it('keeps the exact policy as the default, so no existing key changes meaning', function () {
    expect((new ExpectedAnswer(['reservation']))->policy)->toBe(MatchPolicy::Exact);
});
