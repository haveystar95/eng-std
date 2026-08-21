<?php

declare(strict_types=1);

use App\Modules\Learning\Domain\Service\ChipShuffler;
use App\Modules\Learning\Domain\Service\DistractorSpanFilter;
use App\Modules\Learning\Domain\Service\ModeContentRequirements;
use App\Modules\Learning\Domain\Service\PlayabilityAssessor;
use App\Modules\Learning\Domain\Service\SentenceTokenizer;
use App\Modules\Learning\Domain\ValueObject\ContentGap;
use App\Modules\Learning\Domain\ValueObject\ContentStatus;
use App\Modules\Learning\Domain\ValueObject\ExerciseMode;

function contentRequirements(): ModeContentRequirements
{
    return new ModeContentRequirements(
        new PlayabilityAssessor(new ChipShuffler(), new SentenceTokenizer()),
        new DistractorSpanFilter(),
    );
}

function contentAssessor(): PlayabilityAssessor
{
    return new PlayabilityAssessor(new ChipShuffler(), new SentenceTokenizer());
}

/**
 * Real-shaped terms, one per gap the gate can hit. Each row is
 * [text, example, exampleTranslation, distractor error_spans].
 */
dataset('content fixtures', [
    'bare word, no example' => ['ledger', null, null, []],
    'phrase, no example' => ['bank account', null, null, []],
    'word with a full example and three distinct distractors' => [
        'account',
        'I opened a bank account yesterday.',
        'Вчера я открыл счёт в банке.',
        ['a bank', 'opened', 'yesterday'],
    ],
    'example without a translation' => [
        'account', 'I opened a bank account yesterday.', null, ['a bank', 'opened'],
    ],
    'example that is the term itself' => [
        'Nice to meet you', 'Nice to meet you.', 'Приятно познакомиться.', ['to meet'],
    ],
    'example too short for scramble and dictation' => [
        'apple', 'The apple fell.', 'Яблоко упало.', ['The apple', 'fell'],
    ],
    'example too long for dictation, fine for scramble' => [
        'invoice', 'Could you please send me the invoice for last month by email?',
        'Пришлите мне, пожалуйста, счёт за прошлый месяц по почте.', ['please send', 'by email'],
    ],
    'example that does not contain the term' => [
        'ledger', 'The books were balanced at the end of the quarter.',
        'Книги свели в конце квартала.', ['were balanced'],
    ],
    'distractors that all share one span' => [
        'account', 'I opened a bank account yesterday.', 'Вчера я открыл счёт в банке.',
        ['a bank', 'A BANK', ' a bank '],
    ],
    'distractors with an empty span' => [
        'account', 'I opened a bank account yesterday.', 'Вчера я открыл счёт в банке.', ['', '   '],
    ],
]);

// ── The consistency guard, in the spirit of ModePassportTest ↔ LearningLadder ────────────────────
//
// This service must never be a SECOND opinion about what a term can be drilled in. Every verdict is
// checked against the gate the live session assembler actually runs (TermPlayability::supports via
// PlayabilityAssessor) — if a gate ever moves and this map is not revisited, this is the test that
// catches the drift.

it('agrees with the playability gate on every mode, for every term shape', function (
    string $text, ?string $example, ?string $translation, array $spans,
) {
    $hasExample = $example !== null && $example !== '';
    $usable = $hasExample ? (new DistractorSpanFilter())->countUsable($spans) : 0;
    $playable = contentAssessor()->assess($text, $example, $translation, $usable);
    $assessment = contentRequirements()->assess($text, $example, $translation, $spans);

    foreach (ExerciseMode::cases() as $mode) {
        $verdict = $assessment->for($mode);

        if (ModeContentRequirements::isPoolDependent($mode)) {
            // `pool_dependent` must never be a way of NOT answering a content question — but which
            // question it is dodging changed when description_match arrived. While multiple_choice
            // was the only pool-dependent mode, «has no content gap» and «is pool-dependent» were
            // the same statement, because that mode fits every term. description_match is
            // pool-dependent (its options are other pool words) AND genuinely refusable by the term
            // (no description, no question), so the rule is now the conditional one: content is
            // asked FIRST, and `pool_dependent` is only reported once the term has passed it.
            expect($verdict->status)->toBe(
                $playable->supports($mode) ? ContentStatus::PoolDependent : ContentStatus::Blocked,
                "{$mode->value}: pool-dependence must not hide a content refusal",
            );

            continue;
        }

        expect($verdict->status === ContentStatus::Ok)->toBe(
            $playable->supports($mode),
            "{$mode->value}: verdict disagrees with TermPlayability::supports()",
        );
    }
})->with('content fixtures');

it('carries a machine reason exactly when the card cannot be built, and a human one always', function (
    string $text, ?string $example, ?string $translation, array $spans,
) {
    $assessment = contentRequirements()->assess($text, $example, $translation, $spans);

    foreach ($assessment->modes as $verdict) {
        expect($verdict->explanation)->not->toBe('');
        if ($verdict->status === ContentStatus::Ok) {
            expect($verdict->gap)->toBeNull();
        } else {
            expect($verdict->gap)->toBeInstanceOf(ContentGap::class);
        }
    }
})->with('content fixtures');

it('blocks description_match without a description and defers to the pool with one', function () {
    $without = contentRequirements()->assess('account', 'I opened a bank account.', 'Я открыл счёт.', ['a bank']);
    $with = contentRequirements()->assess(
        'account', 'I opened a bank account.', 'Я открыл счёт.', ['a bank'],
        description: 'A place in a bank where your money is kept, with your name on it.',
    );

    $blocked = $without->for(ExerciseMode::DescriptionMatch);
    expect($blocked->status)->toBe(ContentStatus::Blocked)
        ->and($blocked->gap)->toBe(ContentGap::NoDescription);

    // With one, the term has done its part and the rest is a fact about the session.
    expect($with->for(ExerciseMode::DescriptionMatch)->status)->toBe(ContentStatus::PoolDependent);

    // …and nothing else moved: multiple_choice fits every term, so it stays pool-dependent in both.
    expect($without->for(ExerciseMode::MultipleChoice)->status)->toBe(ContentStatus::PoolDependent)
        ->and($with->for(ExerciseMode::MultipleChoice)->status)->toBe(ContentStatus::PoolDependent);
});

it('covers every exercise mode this build knows, with no gaps', function () {
    $assessment = contentRequirements()->assess('account', 'I opened a bank account.', 'Я открыл счёт.', ['a bank']);

    expect(array_keys($assessment->modes))
        ->toBe(array_map(static fn (ExerciseMode $m): string => $m->value, ExerciseMode::cases()));
});

// ── The reasons themselves ──────────────────────────────────────────────────────────────────────

it('names the gap that actually refused the card', function () {
    $bare = contentRequirements()->assess('ledger', null, null, []);
    expect($bare->for(ExerciseMode::WordBank)->gap)->toBe(ContentGap::SingleWord)
        ->and($bare->for(ExerciseMode::Cloze)->gap)->toBe(ContentGap::NoExample)
        ->and($bare->for(ExerciseMode::Scramble)->gap)->toBe(ContentGap::NoExample)
        ->and($bare->for(ExerciseMode::Dictation)->gap)->toBe(ContentGap::NoExample)
        ->and($bare->for(ExerciseMode::PickCorrect)->gap)->toBe(ContentGap::NoExample);

    $noTranslation = contentRequirements()->assess(
        'account', 'I opened a bank account yesterday.', null, ['a bank', 'opened'],
    );
    expect($noTranslation->for(ExerciseMode::Scramble)->gap)->toBe(ContentGap::NoExampleTranslation)
        ->and($noTranslation->for(ExerciseMode::PickCorrect)->gap)->toBe(ContentGap::NoExampleTranslation)
        // Dictation asks for the AUDIO — it never needed the translation, so it stays buildable.
        ->and($noTranslation->for(ExerciseMode::Dictation)->status)->toBe(ContentStatus::Ok);

    $short = contentRequirements()->assess('apple', 'The apple fell.', 'Яблоко упало.', []);
    expect($short->for(ExerciseMode::Scramble)->gap)->toBe(ContentGap::ExampleTooShort)
        ->and($short->for(ExerciseMode::Dictation)->gap)->toBe(ContentGap::ExampleTooShort);

    $long = contentRequirements()->assess(
        'invoice',
        'Could you please send me the invoice for last month by email?',
        'Пришлите счёт за прошлый месяц.',
        [],
    );
    expect($long->for(ExerciseMode::Dictation)->gap)->toBe(ContentGap::ExampleTooLong)
        // 12 chips: over dictation's ceiling of 10, exactly on scramble's of 12.
        ->and($long->for(ExerciseMode::Scramble)->status)->toBe(ContentStatus::Ok);

    $echo = contentRequirements()->assess('Nice to meet you', 'Nice to meet you.', 'Приятно познакомиться.', ['to meet', 'Nice']);
    expect($echo->for(ExerciseMode::Scramble)->gap)->toBe(ContentGap::ExampleIsTerm)
        ->and($echo->for(ExerciseMode::Dictation)->gap)->toBe(ContentGap::ExampleIsTerm)
        ->and($echo->for(ExerciseMode::PickCorrect)->gap)->toBe(ContentGap::ExampleIsTerm);

    $unrelated = contentRequirements()->assess(
        'ledger', 'The books were balanced at the end of the quarter.', 'Книги свели в конце квартала.', [],
    );
    expect($unrelated->for(ExerciseMode::Cloze)->gap)->toBe(ContentGap::ExampleLacksTerm);
});

it('counts distractors the way the card does — one per error span', function () {
    // Three rows, one span: the assembler would deal ONE wrong option, so pick_correct is refused
    // even though the raw table says three. This is the number the станок's --topup must be read
    // against, and the reason the report counts spans rather than rows.
    $sameSpan = contentRequirements()->assess(
        'account', 'I opened a bank account yesterday.', 'Вчера я открыл счёт в банке.',
        ['a bank', 'A BANK', ' a bank '],
    );
    expect($sameSpan->usableDistractors)->toBe(1)
        ->and($sameSpan->for(ExerciseMode::PickCorrect)->gap)->toBe(ContentGap::TooFewDistractors)
        ->and($sameSpan->for(ExerciseMode::PickCorrect)->explanation)->toContain('годных дистракторов 1');

    $empty = contentRequirements()->assess(
        'account', 'I opened a bank account yesterday.', 'Вчера я открыл счёт в банке.', ['', '   '],
    );
    expect($empty->usableDistractors)->toBe(0);

    $stocked = contentRequirements()->assess(
        'account', 'I opened a bank account yesterday.', 'Вчера я открыл счёт в банке.',
        ['a bank', 'opened', 'yesterday'],
    );
    expect($stocked->usableDistractors)->toBe(3)
        ->and($stocked->for(ExerciseMode::PickCorrect)->status)->toBe(ContentStatus::Ok);
});

it('ignores distractors when the term has no example to hang them off', function () {
    $assessment = contentRequirements()->assess('account', null, null, ['a bank', 'opened', 'yesterday']);

    expect($assessment->usableDistractors)->toBe(0)
        ->and($assessment->for(ExerciseMode::PickCorrect)->gap)->toBe(ContentGap::NoExample);
});

it('never blocks the four modes that fit every term', function (
    string $text, ?string $example, ?string $translation, array $spans,
) {
    $assessment = contentRequirements()->assess($text, $example, $translation, $spans);

    foreach ([ExerciseMode::Typing, ExerciseMode::Listening, ExerciseMode::Speaking, ExerciseMode::Intro] as $mode) {
        expect($assessment->for($mode)->status)->toBe(ContentStatus::Ok);
    }
})->with('content fixtures');

it('answers multiple_choice with pool_dependent rather than a yes it cannot give', function () {
    $assessment = contentRequirements()->assess('ledger', null, null, []);
    $verdict = $assessment->for(ExerciseMode::MultipleChoice);

    expect($verdict->status)->toBe(ContentStatus::PoolDependent)
        ->and($verdict->gap)->toBe(ContentGap::OptionsFromPool)
        ->and($assessment->supports(ExerciseMode::MultipleChoice))->toBeFalse();
});
