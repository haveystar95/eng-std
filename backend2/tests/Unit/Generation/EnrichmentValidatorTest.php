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
    array $synonyms = [],
    array $existingSynonyms = [],
    ?string $termLang = 'en',
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
        synonyms: $synonyms,
        existingSynonyms: $existingSynonyms,
        termLang: $termLang,
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

// Live rows found by the enrich-v2-backfill review (E.2): a bare 's contraction outside the curated
// pronoun list (it's/that's/there's/i'd/...) is not expanded at all — the apostrophe is stripped as
// punctuation and the "s" survives as its own token, so normalize() never folds "How's" onto "How is".
it('scraps a distractor that is the example with a bare apostrophe-s spelled out as "is"', function () {
    $verdict = $this->validator->validate(enrichmentCandidate(
        [new RawDistractor('Hey, Tom! How is it going?', 'tense', 'is', "'s")],
        example: "Hey, Tom! How's it going?",
    ));

    expect($verdict->distractors)->toBeEmpty()
        ->and($verdict->rejectedDistractors)->toBe(1);
});

it('scraps a distractor that is the example with a bare apostrophe-s spelled out, on the pinned example instead', function () {
    $verdict = $this->validator->validate(enrichmentCandidate(
        [new RawDistractor('Here is a little bit about me.', 'tense', 'Here is', "Here's")],
        example: "Here's a little bit about me.",
    ));

    expect($verdict->distractors)->toBeEmpty()
        ->and($verdict->rejectedDistractors)->toBe(1);
});

// ---- contractions: the CLASS, not the four strings that happened to reach a learner ---------------
//
// Every test above this block names one live row, and that is exactly how «закрыт 's» came to be read
// as «класс закрыт»: the curated pronoun list in LexicalNormalizer has i'll/you'll/we'll/they'll and
// not it'll, so the class stayed open in every form nobody had yet seen on a phone. These ask by
// CLITIC instead. Each pair is the same sentence twice — contracted and spelled out — so whichever
// side the станок proposes, the row must die.
dataset('contraction pairs', [
    "'ll — it" => ["It'll be a piece of cake.", 'It will be a piece of cake.'],
    "'ll — he" => ["He'll call you back.", 'He will call you back.'],
    "'ll — she" => ["She'll be late again.", 'She will be late again.'],
    "'ll — that" => ["That'll do nicely.", 'That will do nicely.'],
    "'re — they" => ["They're waiting outside.", 'They are waiting outside.'],
    "'re — we" => ["We're closing at six.", 'We are closing at six.'],
    "'ve — should" => ["You should've called first.", 'You should have called first.'],
    "'ve — would" => ["I would've paid in cash.", 'I would have paid in cash.'],
    "'ve — could" => ["He could've told me.", 'He could have told me.'],
    "n't — mustn't" => ["You mustn't touch that.", 'You must not touch that.'],
    "n't — needn't" => ["You needn't worry at all.", 'You need not worry at all.'],
    "n't — won't" => ["It won't take long.", 'It will not take long.'],
    "n't — can't" => ["I can't hear you.", 'I cannot hear you.'],
    "'d — he" => ["He'd rather stay home.", 'He would rather stay home.'],
    "'d — she" => ["She'd already left.", 'She had already left.'],
    "'d — it" => ["It'd be easier by train.", 'It would be easier by train.'],
    "'m" => ["I'm on my way now.", 'I am on my way now.'],
    "let's" => ["Let's take the next one.", 'Let us take the next one.'],
]);

it('scraps a distractor that only spells the example out, whichever side is contracted', function (string $contracted, string $spelled) {
    // Contraction pinned, distractor spelled out — the live shape («Piece of cake»).
    $spelledOut = $this->validator->validate(enrichmentCandidate(
        [new RawDistractor($spelled, 'tense', $spelled, $contracted)],
        example: $contracted,
    ));

    // And the mirror: the spelled-out form pinned, the contraction offered as the "error".
    $contractedRow = $this->validator->validate(enrichmentCandidate(
        [new RawDistractor($contracted, 'tense', $contracted, $spelled)],
        example: $spelled,
    ));

    expect($spelledOut->distractors)->toBeEmpty()
        ->and($contractedRow->distractors)->toBeEmpty();
})->with('contraction pairs');

it('scraps the live «Piece of cake» row that reached a learner', function () {
    // example_distractors 01M0QZM5HM5FC0WA0N78RHR9YM, станок mech-v13.1. Both options were correct
    // English, so picking the right sentence was marked wrong. Its `error_type` says `modal_to` while
    // there is no modal with `to` anywhere in it — the label lies, which is why nothing keys on labels.
    $verdict = $this->validator->validate(enrichmentCandidate(
        [new RawDistractor("Don't worry about the test — it will be a piece of cake.", 'modal_to', 'it will', "it'll")],
        example: "Don't worry about the test — it'll be a piece of cake.",
    ));

    expect($verdict->distractors)->toBeEmpty()
        ->and($verdict->rejectedDistractors)->toBe(1);
});

it('keeps a distractor whose difference is a real error and not a re-spelling', function () {
    // The guard on the block above: «will be» → «will been» is broken English that merely happens to
    // sit where a contraction could. A check that folded by apostrophe alone would eat it.
    $verdict = $this->validator->validate(enrichmentCandidate(
        [new RawDistractor("Don't worry about the test — it'll been a piece of cake.", 'tense', "it'll been", "it'll be")],
        example: "Don't worry about the test — it'll be a piece of cake.",
    ));

    expect($verdict->distractors)->toHaveCount(1);
});

it('leaves «ain\'t» unexpanded, because nothing can resolve it deterministically', function () {
    // is not / are not / has not / have not — four readings, no rule. The generic n't rule would make
    // «ai not» and a curated entry would have to guess, so the form simply never folds. That means a
    // row like this one survives; a false NEGATIVE here is the direction we chose everywhere else.
    $verdict = $this->validator->validate(enrichmentCandidate(
        [new RawDistractor("It ain't ready yet.", 'tense', "ain't", 'is not')],
        example: 'It is not ready yet.',
    ));

    expect($verdict->distractors)->toHaveCount(1);
});

it('folds a contraction on BOTH sides at once', function () {
    // The case the previous implementation could not reach: it compared each side's readings against
    // the OTHER side's single key, so two sentences that both needed expanding never met.
    expect($this->validator->sentenceEquals("It'll be fine and he's gone.", 'It will be fine and he is gone.'))
        ->toBeTrue();
});

// ---- alphabet: the learner's own language leaking into the sentence being taught ------------------
//
// Nothing was looking at this before. LanguagePurity only ever read the example and the two Russian
// fields, and there it REPORTS rather than refuses, so a distractor sentence could carry any script at
// all. Three such rows are live; the newest was written by the current станок, which is why the prompt
// rule forbidding it (v13.1/00-role.md) is not the answer on its own.

it('scraps a distractor with Cyrillic inside an English sentence', function () {
    // Live: term «start a conversation», станок enrich-v1-topup3, labelled `false_friend`. Every other
    // check passes — the row repairs to the example exactly — and the learner picks the right sentence
    // by alphabet instead of by grammar.
    $verdict = $this->validator->validate(enrichmentCandidate(
        [new RawDistractor('He always knows how to начать a conversation.', 'false_friend', 'начать', 'start')],
        acceptedForms: ['start a conversation'],
        example: 'He always knows how to start a conversation.',
    ));

    expect($verdict->distractors)->toBeEmpty()
        ->and($verdict->rejectedDistractors)->toBe(1);
});

it('scraps a distractor with Cyrillic inside a POLISH sentence', function () {
    // Live: term «Rozmowa», станок mech-v13.1 — the current one. The reason this test exists separately
    // from the English one: LanguagePurity::isClean() has no opinion about `pl` at all, so a check
    // built on it would have passed this row. foreignScriptLetters() knows pl is written in Latin.
    $verdict = $this->validator->validate(enrichmentCandidate(
        [new RawDistractor('Nasza rozmowa trwała к поздna.', 'preposition', 'к поздna', 'do późna')],
        acceptedForms: ['rozmowa'],
        example: 'Nasza rozmowa trwała do późna.',
        termLang: 'pl',
    ));

    expect($verdict->distractors)->toBeEmpty()
        ->and($verdict->rejectedDistractors)->toBe(1);
});

it('keeps a Polish distractor written in Polish, diacritics and all', function () {
    // The guard: «ł ó ż ą» are Latin. A gate that fell back to plain ASCII would scrap every correct
    // Polish row there is.
    $verdict = $this->validator->validate(enrichmentCandidate(
        [new RawDistractor('Nasza rozmowa trwał do późna.', 'tense', 'trwał', 'trwała')],
        acceptedForms: ['rozmowa'],
        example: 'Nasza rozmowa trwała do późna.',
        termLang: 'pl',
    ));

    expect($verdict->distractors)->toHaveCount(1);
});

it('passes a distractor when nobody declared the term\'s language', function () {
    // The playground's manual path: an operator types a term and an example, and no language is
    // declared anywhere. «No opinion» is a pass — the same default LanguagePurity takes for a language
    // it was never taught — because inventing an alphabet is worse than not checking one.
    $verdict = $this->validator->validate(enrichmentCandidate(
        [new RawDistractor('He always knows how to начать a conversation.', 'false_friend', 'начать', 'start')],
        acceptedForms: ['start a conversation'],
        example: 'He always knows how to start a conversation.',
        termLang: null,
    ));

    expect($verdict->distractors)->toHaveCount(1);
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

/**
 * QA-13: both live distractors of the term «organic» carried the correction «organic?» — the card
 * printed «должно быть: organic?» and a learner substituting it back gets «…organic??». The
 * circular check below cannot see it: canonicalize() strips punctuation on both sides, so the
 * repair matches the example either way.
 */
it('scraps a correction that swallowed the sentence-ending mark', function () {
    $verdict = $this->validator->validate(enrichmentCandidate(
        [new RawDistractor('Is the dog food organics?', 'tense', 'organics', 'organic?')],
        example: 'Is the dog food organic?',
        acceptedForms: ['organic'],
        backTranslation: 'organic',
    ));

    expect($verdict->distractors)->toBeEmpty()
        ->and($verdict->rejectedDistractors)->toBe(1);
});

it('scraps the same row when the span is a phrase', function () {
    // The term's other live row: span «in organic», correction «organic?».
    $verdict = $this->validator->validate(enrichmentCandidate(
        [new RawDistractor('Is the dog food in organic?', 'preposition', 'in organic', 'organic?')],
        example: 'Is the dog food organic?',
        acceptedForms: ['organic'],
        backTranslation: 'organic',
    ));

    expect($verdict->distractors)->toBeEmpty();
});

it('keeps the mark when the SPAN has it too — there the correction must carry it', function () {
    // «cost?» → «costs?»: dropping the mark from the correction would delete the sentence's own
    // punctuation when the repair is applied.
    $verdict = $this->validator->validate(enrichmentCandidate(
        [new RawDistractor('How much does this bag costs?', 'tense', 'costs?', 'cost?')],
        example: 'How much does this bag cost?',
        acceptedForms: ['How much does this bag cost?'],
        backTranslation: 'How much does this bag cost?',
    ));

    expect($verdict->distractors)->toHaveCount(1)
        ->and($verdict->distractors[0]->correction)->toBe('cost?');
});

it('is indifferent to a correction whose punctuation is not sentence-ending', function () {
    // A comma or an apostrophe inside the fix is ordinary content, not the sentence's full stop.
    $verdict = $this->validator->validate(enrichmentCandidate(
        [new RawDistractor('I would like to withdraw money from my accounts.', 'tense', 'accounts', "account's")],
        example: "I would like to withdraw money from my account's.",
        acceptedForms: ['withdraw money'],
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

it('finds a short span as a WORD, not as a substring of another word', function () {
    // «on» lives inside «responsible». Applying the repair to the first raw match produced
    // «respforsible» and the row was scrapped as broken, although nothing was wrong with it. Three
    // live rows died this way — tenant, integrate, reservation — all short-preposition spans.
    $verdict = $this->validator->validate(enrichmentCandidate(
        [new RawDistractor('The tenant is responsible on minor repairs.', 'preposition', 'on', 'for')],
        example: 'The tenant is responsible for minor repairs.',
    ));

    expect($verdict->distractors)->toHaveCount(1);
});

it('still finds a span that only ever occurs inside a word', function () {
    // The fallback: with no standalone occurrence the search degrades to the plain one, so this only
    // ever makes the finder smarter and never makes a findable span unfindable.
    $verdict = $this->validator->validate(enrichmentCandidate(
        [new RawDistractor('I would like to withdrawing money from my account.', 'modal_to', 'withdrawin', 'withdraw')],
        example: 'I would like to withdrawg money from my account.',
    ));

    expect($verdict->distractors)->toHaveCount(1);
});

it('dedupes against a stored sibling even when the pack itself is clean', function () {
    $verdict = $this->validator->validate(enrichmentCandidate(
        [validEnrichmentDistractor()],
        existingDistractors: ['I would like to withdrawing money from my account.'],
    ));

    expect($verdict->distractors)->toBeEmpty()
        ->and($verdict->rejectedDistractors)->toBe(1);
});

// Found by the topup-3 proofreading pass (2026-08-19, case #40 in
// docs/enrich-v1-topup3-full-store.md): «mute the phone» → span «the» inside «mute the phone» (the
// term's own accepted form), corrected to «your». The row repairs cleanly to the pinned example and
// passed every check above this one, but "the phone" is the term's OWN canonical wording — marking
// part of it wrong is not a real error, it's the correct answer with a fake correction grafted on.
it("scraps a distractor whose error span sits inside the term's own accepted form", function () {
    $verdict = $this->validator->validate(enrichmentCandidate(
        [new RawDistractor('Please mute the phone during the meeting.', 'article', 'the', 'your')],
        acceptedForms: ['mute the phone'],
        example: 'Please mute your phone during the meeting.',
        backTranslation: 'mute the phone',
    ));

    expect($verdict->distractors)->toBeEmpty()
        ->and($verdict->rejectedDistractors)->toBe(1);
});

// The other half of that rule, and the one it used to get wrong. The check asks whether the span is
// a FRAGMENT OF the term, not whether it touches the term: on a single-word term almost every
// article or agreement error sits right next to the word being taught, and reading that as "the
// term is being marked wrong" scrapped 9 of 21 candidates in one live run — every one of them good.
it('keeps an error whose span merely contains the term, rather than being part of it', function () {
    $verdict = $this->validator->validate(enrichmentCandidate(
        [
            new RawDistractor('The landlord asked for one-month deposit.', 'article', 'one-month deposit', 'a one-month deposit'),
            new RawDistractor('The landlord asked for a one-month deposits.', 'article', 'deposits', 'deposit'),
        ],
        acceptedForms: ['deposit'],
        example: 'The landlord asked for a one-month deposit.',
        backTranslation: 'deposit',
    ));

    expect($verdict->distractors)->toHaveCount(2);
});

it('does not read a span as part of the term when it only shares letters with it', function () {
    // Word boundaries: «the» is a fragment of «mute the phone» and NOT of «theatre».
    $verdict = $this->validator->validate(enrichmentCandidate(
        [new RawDistractor('We went to theatre last night.', 'article', 'to theatre', 'to the theatre')],
        acceptedForms: ['theatre'],
        example: 'We went to the theatre last night.',
        backTranslation: 'theatre',
    ));

    expect($verdict->distractors)->toHaveCount(1);
});

it("keeps a distractor whose span sits outside the term's own accepted form", function () {
    // The ordinary, valid case the check above must not touch: the claimed error is elsewhere in the
    // sentence, not inside the term's own wording.
    $verdict = $this->validator->validate(enrichmentCandidate(
        [new RawDistractor('Please mute the phone during meeting.', 'article', 'meeting', 'the meeting')],
        acceptedForms: ['mute the phone'],
        example: 'Please mute the phone during the meeting.',
        backTranslation: 'mute the phone',
    ));

    expect($verdict->distractors)->toHaveCount(1);
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

/**
 * Regression, found on the first real v12 collection: all twenty terms came back carrying an
 * `ambiguity` finding that said the model had not restored the reference from the Russian —
 * about a shape that produces no translations and was never asked to restore anything.
 *
 * Under `enrich_pack.v2` a null back-translation meant "asked, and the model returned nothing",
 * which is a defect worth a person's time. Under the machinery shape it means "not asked". The
 * candidate has to carry which of the two it is; a worklist that flags every term is a worklist
 * nobody reads, and the real findings drown in it.
 */
it('does not flag ambiguity for a shape that was never asked for a back-translation', function () {
    $verdict = (new EnrichmentValidator())->validate(new EnrichmentCandidate(
        termId: '01TERM',
        acceptedForms: ['to calm down'],
        exampleId: '01EX',
        exampleSentence: 'Take a few deep breaths and try to calm down.',
        translation: 'успокоиться',
        exampleTranslation: 'Сделай несколько глубоких вдохов и постарайся успокоиться.',
        distractors: [],
        variants: [],
        backTranslation: null,
        backTranslationAsked: false,
    ));

    expect($verdict->hasFinding(FindingKind::Ambiguity))->toBeFalse()
        ->and($verdict->findings)->toBe([]);
});

it('still flags a shape that WAS asked and answered with nothing', function () {
    $verdict = (new EnrichmentValidator())->validate(new EnrichmentCandidate(
        termId: '01TERM',
        acceptedForms: ['to calm down'],
        exampleId: '01EX',
        exampleSentence: 'Take a few deep breaths and try to calm down.',
        translation: 'успокоиться',
        exampleTranslation: 'Сделай несколько глубоких вдохов и постарайся успокоиться.',
        distractors: [],
        variants: [],
        backTranslation: null,
    ));

    expect($verdict->hasFinding(FindingKind::Ambiguity))->toBeTrue();
});


// ---- synonyms: the shape rules (SYN-1 Ч.2) -----------------------------------------------------

/** A one-word term, which is where synonyms belong at all. */
function synonymCandidate(array $synonyms, array $existingSynonyms = [], array $variants = []): EnrichmentCandidate
{
    return enrichmentCandidate(
        variants: $variants,
        acceptedForms: ['purpose'],
        example: 'The purpose of this meeting is to agree a date.',
        translation: 'цель',
        exampleTranslation: 'Цель этой встречи — согласовать дату.',
        synonyms: $synonyms,
        existingSynonyms: $existingSynonyms,
    );
}

it('keeps near-synonyms of a single-word term', function () {
    $verdict = $this->validator->validate(synonymCandidate(['goal', 'aim']));

    expect($verdict->synonyms)->toBe(['goal', 'aim'])
        ->and($verdict->rejectedSynonyms)->toBe(0);
});

it('refuses synonyms for a PHRASE — what that would be is a paraphrase', function () {
    $verdict = $this->validator->validate(enrichmentCandidate(
        acceptedForms: ['I would like to withdraw money from my account'],
        synonyms: ['I want to take out cash'],
    ));

    expect($verdict->synonyms)->toBe([])
        // Counted, not swallowed: silence would hide a prompt that had stopped holding the rule.
        ->and($verdict->rejectedSynonyms)->toBe(1);
});

it('refuses a synonym that is really a definition', function () {
    $verdict = $this->validator->validate(synonymCandidate(['the reason for doing something']));

    expect($verdict->synonyms)->toBe([])->and($verdict->rejectedSynonyms)->toBe(1);
});

it('drops a synonym that is the term itself once normalised', function () {
    $verdict = $this->validator->validate(synonymCandidate(['Purpose.']));

    // Not scrap: the model said nothing new, and counting a no-op as a rejection would make a
    // well-behaved run look broken. Same rule the variants follow.
    expect($verdict->synonyms)->toBe([])->and($verdict->rejectedSynonyms)->toBe(0);
});

it('drops a synonym the term already has', function () {
    $verdict = $this->validator->validate(synonymCandidate(['goal', 'aim'], existingSynonyms: ['goal']));

    expect($verdict->synonyms)->toBe(['aim'])->and($verdict->rejectedSynonyms)->toBe(0);
});

it('stores at most three', function () {
    $verdict = $this->validator->validate(synonymCandidate(['goal', 'aim', 'objective', 'intention']));

    expect($verdict->synonyms)->toHaveCount(EnrichmentValidator::MAX_SYNONYMS)
        ->and($verdict->rejectedSynonyms)->toBe(1);
});

it('a text proposed as BOTH a form and a synonym is kept only as the synonym', function () {
    // The narrower reading wins. A synonym stored as a form would be accepted on a listening card,
    // where the learner is being asked what they HEARD — which is how `bank card`, `gadget` and
    // `alight` came to sit in `term_accepted_variants` under the old prompt.
    $verdict = $this->validator->validate(synonymCandidate(
        ['goal'],
        variants: [new RawVariant('goal', 'синоним')],
    ));

    expect($verdict->synonyms)->toBe(['goal'])
        ->and($verdict->variants)->toBe([]);
});
