<?php

declare(strict_types=1);

use App\Modules\Generation\Application\Service\PlanDayComposer;
use App\Modules\Generation\Application\Service\PlanPairCourt;
use App\Modules\Generation\Domain\Service\BasicVocabulary;
use App\Modules\Generation\Domain\Service\PlanDayValidator;

/**
 * THE GATES НАРЯД GEN-1 ADDED (Ч.5.3) — each test breaks exactly one thing in the v0.7 fixture.
 *
 * `planCandidate()` lives in `tests/Pest.php`; the v0.7 fixture is pairs, and `expectsPairs: true`
 * is what switches the pair-only gates on — a day written on v0.6 or earlier is not judged by a
 * rule its prompt never had.
 */
beforeEach(fn () => $this->validator = new PlanDayValidator());

function gen1Candidate(array $edits = []): \App\Modules\Generation\Domain\ValueObject\PlanDayCandidate
{
    return planCandidate($edits, dayFixture: 's1-day1.v0.7.json', expectsDialogue: true, expectsPairs: true);
}

it('lets the v0.7 fixture through every gate, with no counter', function () {
    $day = gen1Candidate();

    expect($this->validator->validate($day))->toBe([])
        ->and(planCodes($this->validator->warnings($day)))->toBe([]);
});

it('refuses a spoken line that carries no speaking_keys — on a paired answer only', function () {
    $broken = gen1Candidate(['say' => [1 => ['speaking_keys' => []]]]);

    $violations = $this->validator->validate($broken);
    expect(planCodes($violations))->toBe([PlanDayValidator::SPEAKING_KEYS_MISSING])
        ->and($violations[0]->array)->toBe('say')
        ->and($violations[0]->index)->toBe(1)
        ->and($violations[0]->field)->toBe('speaking_keys');

    // The same day judged as a v0.6 answer (no pairs expected) is not asked for keys it was
    // never told to write.
    $old = planCandidate(['say' => [1 => ['speaking_keys' => []]]], dayFixture: 's1-day1.v0.7.json', expectsDialogue: true);
    expect($this->validator->validate($old))->toBe([]);
});

it('refuses a numbers card whose value is words, even when the line says them', function () {
    // «twice» stands in the line, so the old literal-match branch let it through; the learner
    // types digits and there was nothing to grade against.
    $broken = gen1Candidate(['numbers' => [0 => ['frame' => 'Take one ___ a day.', 'filler' => 'twice', 'value' => 'twice']]]);

    expect(planCodes($this->validator->validate($broken)))->toBe([PlanDayValidator::NUMBER_VALUE_MISMATCH]);
});

it('keeps counting a missing repair move on a shelved day and not on a paired one', function () {
    // The v0.7 fixture has no «could you repeat» line by design — repairs are the rescue kit's.
    $paired = gen1Candidate();
    expect(planCodes($this->validator->warnings($paired)))->not->toContain(PlanDayValidator::NO_REPAIR);

    $shelved = planCandidate(['say' => [2 => ['frame' => 'Okay, I will wait here.', 'filler' => '']]]);
    expect(planCodes($this->validator->warnings($shelved)))->toContain(PlanDayValidator::NO_REPAIR);
});

it('closes the gap between the slot and the punctuation after it, and touches nothing else', function () {
    expect(PlanDayComposer::assemble('I came in for ___ .', 'lower back pain'))->toBe('I came in for lower back pain.')
        ->and(PlanDayComposer::assemble('Yes, mostly ___ .', 'SQL'))->toBe('Yes, mostly SQL.')
        ->and(PlanDayComposer::assemble('Is it ___ ?', 'here'))->toBe('Is it here?')
        // A space before a WORD is content, not a seam, and stays.
        ->and(PlanDayComposer::assemble('I need ___ now.', 'help'))->toBe('I need help now.')
        ->and(PlanDayComposer::assemble('No slot here.', ''))->toBe('No slot here.');
});

it('reads speaking_keys trimmed, unique and at most two', function () {
    expect(PlanDayComposer::speakingKeysOf([' two years ', 'two years', '', 'about two years', 'a fourth']))
        ->toBe(['two years', 'about two years'])
        ->and(PlanDayComposer::speakingKeysOf('not a list'))->toBe([])
        ->and(PlanDayComposer::speakingKeysOf(null))->toBe([]);
});

it('trusts the four answers of the judge and never its own fits', function () {
    $all = ['answers' => true, 'not_clarification' => true, 'level_fits' => true, 'translation_exact' => true];

    expect(PlanPairCourt::verdictOf([...$all, 'fits' => false, 'reason' => 'x'])['fits'])->toBeTrue()
        // «fits: true» beside a failed question is the live shape this rule exists for.
        ->and(PlanPairCourt::verdictOf([...$all, 'level_fits' => false, 'fits' => true, 'reason' => 'too formal']))
        ->toBe(['fits' => false, 'reason' => 'too formal', 'failed' => ['level_fits']])
        // An answer of the OLD shape — one `fits` and nothing else — reads as four «no».
        ->and(PlanPairCourt::verdictOf(['fits' => true, 'reason' => ''])['failed'])->toBe(PlanPairCourt::CHECKS);
});

it('treats the plural of a listed basic word as basic', function () {
    $basics = new BasicVocabulary();

    expect($basics->isBasic('en', 'days'))->toBeTrue()
        ->and($basics->isBasic('en', 'day'))->toBeTrue()
        ->and($basics->isBasic('en', 'weeks'))->toBeTrue()
        // Not every word ending in «s» is a plural of a basic word.
        ->and($basics->isBasic('en', 'painkillers'))->toBeFalse();
});
