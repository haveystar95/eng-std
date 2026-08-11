<?php

declare(strict_types=1);

use App\Modules\Generation\Domain\Service\EnrichmentValidator;
use App\Modules\Generation\Domain\ValueObject\EnrichmentCandidate;
use App\Modules\Generation\Domain\ValueObject\FindingKind;
use App\Modules\Generation\Domain\ValueObject\RawDistractor;
use App\Modules\Generation\Domain\ValueObject\RawVariant;

beforeEach(fn () => $this->validator = new EnrichmentValidator());

/**
 * @param  list<RawDistractor>  $distractors
 * @param  list<RawVariant>  $variants
 * @param  list<string>  $languageNotes
 */
function enrichmentCandidate(
    array $distractors = [],
    array $variants = [],
    ?string $backTranslation = 'withdraw money',
    array $acceptedForms = ['withdraw money'],
    ?string $example = 'I would like to withdraw money from my account.',
    ?string $translation = 'снять деньги',
    ?string $exampleTranslation = 'Я хотел бы снять деньги со счёта.',
    ?string $exampleId = '01J000000000000000000000EX',
    array $languageNotes = [],
): EnrichmentCandidate {
    return new EnrichmentCandidate(
        termId: '01J000000000000000000000TM',
        acceptedForms: $acceptedForms,
        exampleId: $exampleId,
        exampleSentence: $example,
        translation: $translation,
        exampleTranslation: $exampleTranslation,
        distractors: $distractors,
        variants: $variants,
        backTranslation: $backTranslation,
        languageNotes: $languageNotes,
    );
}

function validEnrichmentDistractor(string $sentence = 'I can to withdraw money from my account.'): RawDistractor
{
    return new RawDistractor($sentence, 'modal_to', 'can to', 'can');
}

// ---- distractors: the scrap rules ------------------------------------------------------------

it('keeps a distractor that is genuinely wrong', function () {
    $verdict = $this->validator->validate(enrichmentCandidate([validEnrichmentDistractor()]));

    expect($verdict->distractors)->toHaveCount(1)
        ->and($verdict->rejectedDistractors)->toBe(0)
        ->and($verdict->proposedDistractors)->toBe(1);
});

it('scraps a distractor our own normalisation folds onto the target', function () {
    // "The withdraw money" → article stripped, lowercased → "withdraw money" = the accepted answer.
    $verdict = $this->validator->validate(enrichmentCandidate([
        new RawDistractor('The withdraw money', 'article', 'The', ''),
    ]));

    expect($verdict->distractors)->toBeEmpty()
        ->and($verdict->rejectedDistractors)->toBe(1);
});

it('scraps a contraction-only rewrite, which the grader accepts as correct', function () {
    // The server expands contractions before comparing, so this is the example, not an error.
    $verdict = $this->validator->validate(enrichmentCandidate(
        [new RawDistractor("I'd like to withdraw money from my account.", 'tense', "I'd", 'I would')],
        acceptedForms: ['I would like to withdraw money from my account.'],
        backTranslation: 'I would like to withdraw money from my account.',
    ));

    expect($verdict->distractors)->toBeEmpty()
        ->and($verdict->rejectedDistractors)->toBe(1);
});

it('scraps a distractor whose error_span is not in its own sentence', function () {
    $verdict = $this->validator->validate(enrichmentCandidate([
        new RawDistractor('I can withdraw money from my account.', 'modal_to', 'can to', 'can'),
    ]));

    expect($verdict->distractors)->toBeEmpty()
        ->and($verdict->rejectedDistractors)->toBe(1);
});

it('accepts an error_span that differs only in case from the sentence', function () {
    $verdict = $this->validator->validate(enrichmentCandidate([
        new RawDistractor('Can to withdraw money is my plan.', 'modal_to', 'can to', 'can'),
    ]));

    expect($verdict->distractors)->toHaveCount(1);
});

it('scraps a distractor with an unknown error type', function () {
    $verdict = $this->validator->validate(enrichmentCandidate([
        new RawDistractor('I withdraws money.', 'agreement', 'withdraws', 'withdraw'),
    ]));

    expect($verdict->distractors)->toBeEmpty()
        ->and($verdict->rejectedDistractors)->toBe(1);
});

it('scraps a distractor identical to the pinned example', function () {
    $verdict = $this->validator->validate(enrichmentCandidate([
        new RawDistractor('I would like to withdraw money from my account.', 'article', 'money', 'the money'),
    ]));

    expect($verdict->distractors)->toBeEmpty();
});

it('scraps every distractor when the term has no pinned example to hang them on', function () {
    $verdict = $this->validator->validate(enrichmentCandidate([validEnrichmentDistractor()], example: null, exampleId: null));

    expect($verdict->distractors)->toBeEmpty()
        ->and($verdict->rejectedDistractors)->toBe(1);
});

it('caps distractors at three and dedupes them', function () {
    $verdict = $this->validator->validate(enrichmentCandidate([
        validEnrichmentDistractor('I can to withdraw money.'),
        validEnrichmentDistractor('I can to withdraw money.'),        // duplicate
        validEnrichmentDistractor('I must to withdraw money.'),
        validEnrichmentDistractor('You can to withdraw money.'),
        validEnrichmentDistractor('She can to withdraw money.'),      // over the cap
    ]));

    expect($verdict->distractors)->toHaveCount(EnrichmentValidator::MAX_DISTRACTORS);
});

// ---- variants ---------------------------------------------------------------------------------

it('keeps a genuinely different correct variant', function () {
    $verdict = $this->validator->validate(enrichmentCandidate(variants: [new RawVariant('take out money', 'то же значение')]));

    expect($verdict->variants)->toHaveCount(1)
        ->and($verdict->variants[0]->text)->toBe('take out money');
});

it('drops a variant that only restates an already accepted form', function () {
    // "The Withdraw Money." normalises to the target — storing it would buy nothing.
    $verdict = $this->validator->validate(enrichmentCandidate(variants: [new RawVariant('The Withdraw Money.', null)]));

    expect($verdict->variants)->toBeEmpty();
});

it('dedupes variants against each other', function () {
    $verdict = $this->validator->validate(enrichmentCandidate(variants: [
        new RawVariant('take out money', null),
        new RawVariant('Take out money!', null),
    ]));

    expect($verdict->variants)->toHaveCount(1);
});

// ---- the collision: correct and wrong at the same time ----------------------------------------

it('flags the term when a proposed variant is also a proposed distractor, and drops the distractor', function () {
    $verdict = $this->validator->validate(enrichmentCandidate(
        distractors: [new RawDistractor('take out money', 'preposition', 'out', 'off')],
        variants: [new RawVariant('take out money', 'то же значение')],
    ));

    expect($verdict->distractors)->toBeEmpty()
        ->and($verdict->variants)->toHaveCount(1)
        ->and($verdict->hasFinding(FindingKind::VariantConflict))->toBeTrue();
});

it('does not raise a conflict for a distractor that merely folds onto the target', function () {
    $verdict = $this->validator->validate(enrichmentCandidate([
        new RawDistractor('The withdraw money', 'article', 'The', ''),
    ]));

    expect($verdict->hasFinding(FindingKind::VariantConflict))->toBeFalse()
        ->and($verdict->rejectedDistractors)->toBe(1);
});

// ---- ambiguity: the back-translation check ----------------------------------------------------

it('passes when the back-translation lands on the target', function () {
    $verdict = $this->validator->validate(enrichmentCandidate());

    expect($verdict->hasFinding(FindingKind::Ambiguity))->toBeFalse();
});

it('flags ambiguity when the back-translation is something else entirely', function () {
    $verdict = $this->validator->validate(enrichmentCandidate(backTranslation: 'take a photo'));

    expect($verdict->hasFinding(FindingKind::Ambiguity))->toBeTrue();
});

it('does not flag ambiguity when a variant covers the back-translation', function () {
    $verdict = $this->validator->validate(enrichmentCandidate(
        variants: [new RawVariant('take out money', null)],
        backTranslation: 'take out money',
    ));

    expect($verdict->hasFinding(FindingKind::Ambiguity))->toBeFalse();
});

it('flags ambiguity when the model returned no back-translation at all', function () {
    $verdict = $this->validator->validate(enrichmentCandidate(backTranslation: ''));

    expect($verdict->hasFinding(FindingKind::Ambiguity))->toBeTrue();
});

// ---- language purity --------------------------------------------------------------------------

it('flags Ukrainian letters in a Russian field', function () {
    $verdict = $this->validator->validate(enrichmentCandidate(translation: 'знімати гроші'));

    expect($verdict->hasFinding(FindingKind::Language))->toBeTrue();
});

it('flags non-latin letters in an English field', function () {
    $verdict = $this->validator->validate(enrichmentCandidate(example: 'I would like to снять money.'));

    expect($verdict->hasFinding(FindingKind::Language))->toBeTrue();
});

it('passes clean Russian and English', function () {
    $verdict = $this->validator->validate(enrichmentCandidate());

    expect($verdict->hasFinding(FindingKind::Language))->toBeFalse();
});

it('carries the model lexis note through, since a charset check cannot see shared-Cyrillic UA words', function () {
    // «здаватися» is spelled entirely in letters Russian also has — only the model can catch it.
    $verdict = $this->validator->validate(enrichmentCandidate(languageNotes: ['«здаватися» — украинское, по-русски «сдаваться».']));

    expect($verdict->hasFinding(FindingKind::Language))->toBeTrue();
});
