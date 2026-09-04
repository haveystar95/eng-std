<?php

declare(strict_types=1);

use App\Modules\Generation\Domain\Service\PlanDayValidator;
use App\Modules\Generation\Domain\ValueObject\PlanViolation;

/**
 * THE FOUR GATES OF P2 v0.5's `dialogue` — наряд DAY-2 Ч.1.2, канон `docs/plan-dialogue.md` §9.
 *
 * The chain is what the dialogue screen plays, so a broken chain is a scene that cannot be spoken.
 * Three of the four refuse the day and one counts; each test below breaks exactly ONE thing in a
 * fixture that is otherwise clean, so a failure names the rule it broke.
 *
 * The helpers (`planCandidate`, `planCodes`) live in `PlanDayValidatorTest.php` — Pest loads both
 * files into one suite, and duplicating a fixture builder is how two files come to disagree about
 * what a clean day is.
 */
beforeEach(fn () => $this->validator = new PlanDayValidator());

/** The S1 fixture's own chain, as raw turns — the thing every test below breaks one piece of. */
function planChain(): array
{
    /** @var list<array{turn: string, ref: string}> $turns */
    $turns = planFixture('s1-day1.v0.4.json')['dialogue'];

    return $turns;
}

it('lets a scene through when its chain alternates and every ref lands', function () {
    $day = planCandidate(expectsDialogue: true);

    expect($this->validator->validate($day))->toBe([])
        // Every `say` and every `ask` of the fixture appears in its chain, so nothing is uncovered.
        ->and(planCodes($this->validator->warnings($day)))->toBe([]);
});

it('refuses a fresh answer that came back with no chain at all', function () {
    $day = planCandidate(dialogue: [], expectsDialogue: true);

    expect(planCodes($this->validator->validate($day)))->toBe([PlanDayValidator::DIALOGUE_MISSING]);
});

it('says nothing about a day whose prompt never asked for a chain', function () {
    // Every plan written on v0.4 and still on the phone. Its days are re-judged whole on every
    // repair, and a gate the prompt never asked for would refuse material the learner is halfway
    // through — for a field the answer was never told to write.
    $day = planCandidate(dialogue: [], expectsDialogue: false);

    expect($this->validator->validate($day))->toBe([]);
});

it('refuses a turn pointing at a card the day does not have', function () {
    $broken = planChain();
    $broken[1] = ['turn' => 'you', 'ref' => 'say[9]'];

    $violations = $this->validator->validate(planCandidate(dialogue: $broken, expectsDialogue: true));

    expect(planCodes($violations))->toBe([PlanDayValidator::DIALOGUE_REF_INVALID]);

    // ADDRESSED AT WHAT THE REF CLAIMS — which is what puts «say[9]» in `fail_reason` where a person
    // reads it. The address names no card of the answer, so the repairer refuses to patch the day
    // card by card and it goes back whole; that is the honest outcome, because the chain is not a
    // card and there is nothing for P2R to rewrite.
    $violation = $violations[0];
    expect($violation)->toBeInstanceOf(PlanViolation::class)
        ->and($violation->isAddressed())->toBeTrue()
        ->and($violation->array)->toBe('say')
        ->and($violation->index)->toBe(9)
        ->and($violation->address())->toContain('say[9]');
});

it('refuses a role turn that speaks from the learner’s own shelf', function () {
    $broken = planChain();
    $broken[0] = ['turn' => 'role', 'ref' => 'say[0]'];

    expect(planCodes($this->validator->validate(planCandidate(dialogue: $broken, expectsDialogue: true))))
        ->toBe([PlanDayValidator::DIALOGUE_REF_INVALID]);
});

it('refuses a ref that is not an address at all', function () {
    $broken = planChain();
    $broken[0] = ['turn' => 'role', 'ref' => 'the first thing they say'];

    expect(planCodes($this->validator->validate(planCandidate(dialogue: $broken, expectsDialogue: true))))
        ->toBe([PlanDayValidator::DIALOGUE_REF_INVALID]);
});

it('refuses two turns of the same side in a row — a monologue is not an exchange', function () {
    $broken = planChain();
    $broken[1] = ['turn' => 'role', 'ref' => 'hear[2]'];

    expect(planCodes($this->validator->validate(planCandidate(dialogue: $broken, expectsDialogue: true))))
        ->toBe([PlanDayValidator::DIALOGUE_NOT_ALTERNATING]);
});

it('refuses a turn that is neither side, and does not also call its ref broken', function () {
    // One defect, one violation. A turn with an invented side has no allowed shelves, so every ref
    // it carries would read as invalid too — and a verdict that names two rules for one mistake
    // sends a repair call after the wrong thing.
    $broken = planChain();
    $broken[0] = ['turn' => 'narrator', 'ref' => 'hear[0]'];

    expect(planCodes($this->validator->validate(planCandidate(dialogue: $broken, expectsDialogue: true))))
        ->toBe([PlanDayValidator::DIALOGUE_NOT_ALTERNATING]);
});

it('counts a reply the conversation never reaches, and does not refuse the day for it', function () {
    // «Every say and ask item should appear in the dialogue at least once» — a WARNING, because a
    // reply left out of the chain is still a card on its shelf, still climbs its ladder and still
    // comes back in the seam. What it loses is its place in the scene.
    $short = array_slice(planChain(), 0, 4);
    $day = planCandidate(dialogue: $short, expectsDialogue: true);

    expect($this->validator->validate($day))->toBe([])
        ->and(planCodes($this->validator->warnings($day)))->toBe([PlanDayValidator::DIALOGUE_UNCOVERED]);
});

it('says nothing about a hear card the conversation never reaches', function () {
    // The prompt's own wording: hear items «may appear once». Permission, not a quota — a scene
    // where the other person's closing line is never answered is a real scene.
    $withoutLastHear = array_values(array_filter(
        planChain(),
        static fn (array $turn): bool => $turn['ref'] !== 'hear[3]',
    ));

    expect(planCodes($this->validator->warnings(
        planCandidate(dialogue: $withoutLastHear, expectsDialogue: true),
    )))->toBe([]);
});
