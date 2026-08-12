<?php

declare(strict_types=1);

use App\Modules\Generation\Domain\Service\EnrichmentValidator;
use App\Modules\Generation\Domain\ValueObject\EnrichmentCandidate;
use App\Modules\Generation\Domain\ValueObject\FindingKind;
use App\Modules\Generation\Domain\ValueObject\RawDistractor;
use App\Modules\Generation\Domain\ValueObject\RawLanguageNote;
use App\Modules\Generation\Domain\ValueObject\RawVariant;

beforeEach(fn () => $this->validator = new EnrichmentValidator());

/**
 * @param  list<RawDistractor>  $distractors
 * @param  list<RawVariant>  $variants
 * @param  list<RawLanguageNote>  $languageNotes
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
    array $existingDistractors = [],
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
        existingDistractors: $existingDistractors,
    );
}

/**
 * A distractor that survives every rule: it is the pinned example with exactly one fragment broken,
 * and putting the correction back where the span is gives the example again.
 */
function validEnrichmentDistractor(string $sentence = 'I would like to withdrawing money from my account.'): RawDistractor
{
    return new RawDistractor($sentence, 'modal_to', 'to withdrawing', 'to withdraw');
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

// Found on the device: «Excuse me, is this seat **take**?» — the model marked the error, which hands
// the learner the answer. The card is then solved by hunting for asterisks, not by reading grammar.
it('strips markdown emphasis from the sentence, the span and the correction', function () {
    $verdict = $this->validator->validate(enrichmentCandidate(
        [new RawDistractor('Excuse me, is this seat **take**?', 'tense', '**take**', '**taken**')],
        example: 'Excuse me, is this seat taken?',
    ));

    expect($verdict->distractors)->toHaveCount(1)
        ->and($verdict->distractors[0]->sentence)->toBe('Excuse me, is this seat take?')
        ->and($verdict->distractors[0]->errorSpan)->toBe('take')
        ->and($verdict->distractors[0]->correction)->toBe('taken');
});

it('keeps the span findable after stripping, rather than scrapping an over-decorated row', function () {
    // The span arrives bare while the sentence decorates it — stripping both sides is what keeps the
    // relation true instead of failing a row whose only sin was emphasis.
    $verdict = $this->validator->validate(enrichmentCandidate(
        [new RawDistractor('Excuse me, is this **seats** taken?', 'tense', 'seats', 'seat')],
        example: 'Excuse me, is this seat taken?',
    ));

    expect($verdict->distractors)->toHaveCount(1)
        ->and($verdict->distractors[0]->sentence)->toBe('Excuse me, is this seats taken?');
});

it('strips emphasis from a variant too', function () {
    $verdict = $this->validator->validate(enrichmentCandidate(
        variants: [new RawVariant('__take out money__', null)],
    ));

    expect($verdict->variants[0]->text)->toBe('take out money');
});

it('strips emphasis from a variant NOTE — the field the sweep went past', function () {
    // The sweep of 46f9076 cleaned sentences, spans and corrections. The note is prose a person reads
    // for exactly the same reason, and it was not on that list.
    $verdict = $this->validator->validate(enrichmentCandidate(
        variants: [new RawVariant('take out money', '_то же значение_')],
    ));

    expect($verdict->variants[0]->note)->toBe('то же значение');
});

it('scraps a distractor whose error_span is not in its own sentence', function () {
    $verdict = $this->validator->validate(enrichmentCandidate([
        new RawDistractor('I can withdraw money from my account.', 'modal_to', 'can to', 'can'),
    ]));

    expect($verdict->distractors)->toBeEmpty()
        ->and($verdict->rejectedDistractors)->toBe(1);
});

it('accepts an error_span that differs only in case from the sentence', function () {
    // The span is written lowercase while the sentence capitalises it — a sentence-initial capital
    // must not fail an otherwise good row.
    $verdict = $this->validator->validate(enrichmentCandidate(
        [new RawDistractor('Can to withdraw money from my account?', 'modal_to', 'can to', 'Can I')],
        example: 'Can I withdraw money from my account?',
    ));

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
        new RawDistractor('I would like to withdrawing money from my account.', 'modal_to', 'to withdrawing', 'to withdraw'),
        new RawDistractor('I would like to withdrawing money from my account.', 'modal_to', 'to withdrawing', 'to withdraw'), // duplicate
        new RawDistractor('I would likes to withdraw money from my account.', 'tense', 'likes', 'like'),
        new RawDistractor('I would like to withdraw money for my account.', 'preposition', 'for', 'from'),
        new RawDistractor('I would like to withdraw moneys from my account.', 'tense', 'moneys', 'money'), // over the cap
    ]));

    expect($verdict->distractors)->toHaveCount(EnrichmentValidator::MAX_DISTRACTORS);
});

// ---- the four v2 checks, each with the row from store5 that made it necessary -------------------

it('scraps a distractor that is the example with the contraction spelled out', function () {
    // «run a temperature». The example is «He's been running…», the станок offered «He has been
    // running…» as the WRONG sentence — one and the same answer, and the card would mark the learner
    // wrong for picking it. It survived because the normaliser did not fold «'s been» onto «has been».
    $verdict = $this->validator->validate(enrichmentCandidate(
        [new RawDistractor('He has been running a temperature since last night.', 'tense', 'has been', 'has')],
        example: "He's been running a temperature since last night.",
    ));

    expect($verdict->distractors)->toBeEmpty()
        ->and($verdict->rejectedDistractors)->toBe(1);
});

it('scraps a distractor that only re-types the apostrophe of a sibling already stored', function () {
    // «Can I get this to go?» carried «I'd like the pasta for go, please.» from the first run, and the
    // top-up added the same sentence with a typographic apostrophe. The pack dedupes against itself
    // only, so the twin was invisible until the STORED rows joined the comparison.
    $verdict = $this->validator->validate(enrichmentCandidate(
        [new RawDistractor('I’d like the pasta for go, please.', 'preposition', 'for go', 'to go')],
        example: "I'd like the pasta to go, please.",
        existingDistractors: ["I'd like the pasta for go, please."],
    ));

    expect($verdict->distractors)->toBeEmpty()
        ->and($verdict->rejectedDistractors)->toBe(1);
});

it('scraps a correction identical to its own span', function () {
    // The same «run a temperature» row: `has been` → `has been`. The card underlines a fragment and
    // then offers the same fragment back as the fix.
    $verdict = $this->validator->validate(enrichmentCandidate(
        [new RawDistractor('He has been running a temperature since last night.', 'tense', 'has been', 'has been')],
        example: 'He was running a temperature since last night.',
    ));

    expect($verdict->distractors)->toBeEmpty()
        ->and($verdict->rejectedDistractors)->toBe(1);
});

it('keeps an article correction, whose whole content is the article normalize() drops', function () {
    // The guard on the check above: «bank account» → «a bank account» is not a no-op, and comparing
    // through normalize() (which throws the leading article away) would scrap the entire class.
    $verdict = $this->validator->validate(enrichmentCandidate(
        [new RawDistractor('I need bank account to receive my salary.', 'article', 'bank account', 'a bank account')],
        example: 'I need a bank account to receive my salary.',
    ));

    expect($verdict->distractors)->toHaveCount(1);
});

it('scraps a distractor whose own repair does not give back the example', function () {
    // «online banking»: `at` → `services` turns «Do you offer online banking at services?» into
    // «…online banking services services?». The span was never the error, so the underline and the
    // fix shown to the learner are both pointing at the wrong place.
    $verdict = $this->validator->validate(enrichmentCandidate(
        [new RawDistractor('Do you offer online banking at services?', 'preposition', 'at', 'services')],
        example: 'Do you offer online banking services?',
    ));

    expect($verdict->distractors)->toBeEmpty()
        ->and($verdict->rejectedDistractors)->toBe(1);
});

it('scraps a row whose correction repairs to a different word than the example uses', function () {
    // «cold»: the example is «I think I have a cold.», the row corrects `the cold` → `cold`, so the
    // learner is taught «I think I have cold». Grammatically the distractor is fine too — this is the
    // determiner swap the prompt now forbids, caught here as well because the repair misses.
    $verdict = $this->validator->validate(enrichmentCandidate(
        [new RawDistractor('I think I have the cold.', 'article', 'the cold', 'cold')],
        example: 'I think I have a cold.',
    ));

    expect($verdict->distractors)->toBeEmpty();
});

it('dedupes against a stored sibling even when the pack itself is clean', function () {
    $verdict = $this->validator->validate(enrichmentCandidate(
        [validEnrichmentDistractor()],
        existingDistractors: ['I would like to withdrawing money from my account.'],
    ));

    expect($verdict->distractors)->toBeEmpty()
        ->and($verdict->rejectedDistractors)->toBe(1);
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

// The failure that reaches LIVE grading: shown the term and its example, the model answers the wrong
// question and returns a sentence. Stored, it makes a one-word card accept a whole sentence.
it('rejects a variant that is a sentence rather than an alternative to the term', function () {
    $verdict = $this->validator->validate(enrichmentCandidate(
        variants: [new RawVariant('You can take a break in the break room during lunch.', 'синоним')],
        acceptedForms: ['break room'],
        backTranslation: 'break room',
    ));

    expect($verdict->variants)->toBeEmpty()
        ->and($verdict->rejectedVariants)->toBe(1);
});

it('keeps a variant within the token budget (a compound written as two words)', function () {
    $verdict = $this->validator->validate(enrichmentCandidate(
        variants: [new RawVariant('work station', 'то же слово раздельно')],
        acceptedForms: ['workstation'],
        backTranslation: 'workstation',
    ));

    expect($verdict->variants)->toHaveCount(1)
        ->and($verdict->rejectedVariants)->toBe(0);
});

it('scales the budget with the target, so a phrase may have a phrase-length variant', function () {
    $verdict = $this->validator->validate(enrichmentCandidate(
        variants: [new RawVariant('show someone the ropes at work', null)],   // 6 tokens vs 3 + 2 → out
        acceptedForms: ['show the ropes'],                                    // 3 tokens
        backTranslation: 'show the ropes',
    ));

    expect($verdict->variants)->toBeEmpty();

    $ok = $this->validator->validate(enrichmentCandidate(
        variants: [new RawVariant('show somebody the ropes', null)],          // 4 tokens → within 5
        acceptedForms: ['show the ropes'],
        backTranslation: 'show the ropes',
    ));

    expect($ok->variants)->toHaveCount(1);
});

it('does not count an already-accepted restatement as scrap', function () {
    // A no-op, not a rejection — counting it would make a well-behaved run look broken.
    $verdict = $this->validator->validate(enrichmentCandidate(variants: [new RawVariant('The Withdraw Money.', null)]));

    expect($verdict->variants)->toBeEmpty()
        ->and($verdict->rejectedVariants)->toBe(0);
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

it('suppresses ambiguity for a one-inflection near miss', function () {
    $verdict = $this->validator->validate(enrichmentCandidate(
        acceptedForms: ['company policies'],
        backTranslation: 'company policy',
    ));

    expect($verdict->hasFinding(FindingKind::Ambiguity))->toBeFalse();
});

it('still flags ambiguity when the differing token is a different word', function () {
    $verdict = $this->validator->validate(enrichmentCandidate(
        acceptedForms: ['break room'],
        backTranslation: 'rest room',
    ));

    expect($verdict->hasFinding(FindingKind::Ambiguity))->toBeTrue();
});

it('suppresses ambiguity when a stored VARIANT is the near miss', function () {
    // The tolerance runs against the whole key, variants included — so a variant this same pack
    // proposed can retire the flag.
    $verdict = $this->validator->validate(enrichmentCandidate(
        variants: [new RawVariant('greet the team', null)],
        acceptedForms: ['meet the team'],
        backTranslation: 'greet the teams',
    ));

    expect($verdict->hasFinding(FindingKind::Ambiguity))->toBeFalse();
});

it('flags ambiguity when the model returned no back-translation at all', function () {
    $verdict = $this->validator->validate(enrichmentCandidate(backTranslation: ''));

    expect($verdict->hasFinding(FindingKind::Ambiguity))->toBeTrue();
});

// ---- language purity --------------------------------------------------------------------------

it('flags Ukrainian letters in a Russian field as LEAKAGE, not as a generic language problem', function () {
    // The kind decides the repair: leakage means regenerate the item, so it must not land in the
    // same bucket as a typo (which is repaired by editing the wording).
    $verdict = $this->validator->validate(enrichmentCandidate(translation: 'знімати гроші'));

    expect($verdict->hasFinding(FindingKind::UaLeakage))->toBeTrue()
        ->and($verdict->hasFinding(FindingKind::MisspelledOrNonword))->toBeFalse();
});

it('classifies the model note that a translation is not a real word', function () {
    // The live case: `colleague` → «колледка». All-Russian letters, so no charset check can see it;
    // only the model's judgement catches it, and it is an EDIT, not a regeneration.
    $verdict = $this->validator->validate(enrichmentCandidate(
        languageNotes: [new RawLanguageNote('misspelled_or_nonword', '«колледка» — не слово; должно быть «коллега».')],
    ));

    expect($verdict->hasFinding(FindingKind::MisspelledOrNonword))->toBeTrue()
        ->and($verdict->hasFinding(FindingKind::UaLeakage))->toBeFalse();
});

it('classifies a model-reported украинизм as leakage', function () {
    $verdict = $this->validator->validate(enrichmentCandidate(
        // Spelled entirely in letters Russian also has — invisible to any charset check.
        languageNotes: [new RawLanguageNote('ua_leakage', '«здаватися» — украинское, по-русски «сдаваться».')],
    ));

    expect($verdict->hasFinding(FindingKind::UaLeakage))->toBeTrue();
});

it('degrades an unknown note class to the coarse language kind instead of dropping it', function () {
    // A newer prompt may add a class; a run must not lose the finding because of it.
    $verdict = $this->validator->validate(enrichmentCandidate(
        languageNotes: [new RawLanguageNote('some_future_class', 'что-то не так со словом')],
    ));

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

// Two of eight language findings on the store run argued themselves down. The prompt now forbids it;
// this is the guarantee behind the request.
it('drops a language note that concludes there is no problem', function () {
    $verdict = $this->validator->validate(enrichmentCandidate(languageNotes: [
        new RawLanguageNote('misspelled_or_nonword', 'банкомат не является ошибкой.'),
    ]));

    expect($verdict->hasLanguageFinding())->toBeFalse();
});

it('drops a self-refuting note whatever the case', function () {
    $verdict = $this->validator->validate(enrichmentCandidate(languageNotes: [
        new RawLanguageNote('misspelled_or_nonword', '«куриный» должно быть «куриный», Но Это Корректно'),
    ]));

    expect($verdict->hasLanguageFinding())->toBeFalse();
});

it('keeps a note that names a real problem and does not retract it', function () {
    $verdict = $this->validator->validate(enrichmentCandidate(languageNotes: [
        new RawLanguageNote('misspelled_or_nonword', '«колледка» — не слово; должно быть «коллега».'),
    ]));

    expect($verdict->hasFinding(FindingKind::MisspelledOrNonword))->toBeTrue();
});

it('scrubs JSON debris the model leaked into a variant note', function () {
    // Legal JSON, garbage on screen — seen live on «Синонимы."},{».
    $verdict = $this->validator->validate(enrichmentCandidate(
        variants: [new RawVariant('take out money', 'Синонимы."},{')],
    ));

    expect($verdict->variants[0]->note)->toBe('Синонимы.');
});

it('keeps quotes that are part of the prose', function () {
    $verdict = $this->validator->validate(enrichmentCandidate(
        variants: [new RawVariant('take out money', 'синоним к слову "залог"')],
    ));

    expect($verdict->variants[0]->note)->toBe('синоним к слову "залог"');
});

it('nulls a note that is nothing but debris, keeping the variant', function () {
    $verdict = $this->validator->validate(enrichmentCandidate(
        variants: [new RawVariant('take out money', '"},{')],
    ));

    expect($verdict->variants)->toHaveCount(1)
        ->and($verdict->variants[0]->note)->toBeNull();
});

it('ignores a note with an empty detail', function () {
    $verdict = $this->validator->validate(enrichmentCandidate(
        languageNotes: [new RawLanguageNote('misspelled_or_nonword', '   ')],
    ));

    expect($verdict->hasLanguageFinding())->toBeFalse();
});
