<?php

declare(strict_types=1);

use App\Modules\Learning\Domain\Service\PlanSessionSections as S;
use App\Modules\Learning\Domain\Service\PlanSittings;

/** `n` tasks of one section, as the flat list the cutter reads. */
function part(string $section, int $count): array
{
    return array_fill(0, $count, $section);
}

it('cuts a day into sittings of about the budget, on section boundaries only', function () {
    $sections = [
        ...part(S::WARMUP, 6),
        ...part(S::WORDS, 9),
        ...part(S::DIALOGUE_INTRO, 4),
        ...part(S::DIALOGUE, 8),
        ...part(S::NUMBERS, 3),
    ];

    // Budget 12: warm-up (6) + words (9) would be 15, so the words open a second присест.
    expect(PlanSittings::cut($sections, 12))->toBe([6, 9, 12, 3]);
});

it('never splits a section, even when the section is longer than the whole budget', function () {
    $sections = [...part(S::WARMUP, 2), ...part(S::DIALOGUE, 20), ...part(S::NUMBERS, 1)];

    // «Секцию не рвать; если секция длиннее бюджета — она и есть присест.»
    expect(PlanSittings::cut($sections, 5))->toBe([2, 20, 1]);
});

it('adds up to the whole day, so nothing the learner passed can burn', function () {
    $sections = [
        ...part(S::WARMUP, 7), ...part(S::WORDS, 11), ...part(S::DIALOGUE_INTRO, 5),
        ...part(S::DIALOGUE, 9), ...part(S::NUMBERS, 4), ...part(S::REVIEW, 6),
    ];

    foreach ([1, 3, 8, 15, 40, 1000] as $budget) {
        $sittings = PlanSittings::cut($sections, $budget);

        expect(array_sum($sittings))->toBe(count($sections))
            // An empty присест is a «Присест N пройден» screen over nothing.
            ->and(array_filter($sittings, static fn (int $n): bool => $n <= 0))->toBe([]);
    }
});

it('is one sitting when the whole day fits the budget', function () {
    expect(PlanSittings::cut([...part(S::WARMUP, 3), ...part(S::WORDS, 4)], 20))->toBe([7]);
});

it('answers an empty day with no sittings at all', function () {
    expect(PlanSittings::cut([], 20))->toBe([]);
});

it('treats a budget of zero as one card, rather than as no day', function () {
    expect(PlanSittings::cut([...part(S::WARMUP, 2), ...part(S::DIALOGUE, 2)], 0))->toBe([2, 2]);
});

it('never hands out a sitting longer than forty tasks, whatever the minutes buy (С-12)', function () {
    // The stand's own day: 68 tasks over the canon's shelves, on the learner's twenty minutes. The
    // budget alone never cut it — `sittings` came back `[68]`, and the «присест пройден» screen was
    // never once seen in the whole run. A day-scene is two sittings for anybody.
    $day = [
        ...part(S::WARMUP, 10), ...part(S::WORDS, 24),
        ...part(S::DIALOGUE_INTRO, 8), ...part(S::DIALOGUE, 16), ...part(S::NUMBERS, 10),
    ];
    expect($day)->toHaveCount(68);

    // 20 minutes at the measured 16 s a card is a budget of 75 — more than the day holds.
    // The cut still falls on a PART boundary: warm-up + words, then the scene's own parts.
    expect(PlanSittings::cut($day, 75))->toBe([34, 34]);

    // …and the ceiling binds no matter how the arithmetic is arrived at.
    foreach ([41, 75, 150, 1000] as $budget) {
        foreach (PlanSittings::cut($day, $budget) as $size) {
            expect($size)->toBeLessThanOrEqual(PlanSittings::MAX_TASKS_PER_SITTING);
        }
    }
});

it('re-opens a section that comes back later as a section of its own', function () {
    // The cutter reads the list it is given and does not reorder it: two runs of the same key are
    // two parts, because a boundary is «the part changed», not «this part has appeared before».
    // Which is exactly what a sitting holding two scenes' conversations looks like.
    expect(PlanSittings::cut([...part(S::DIALOGUE, 2), ...part(S::DIALOGUE_INTRO, 2), ...part(S::DIALOGUE, 2)], 3))
        ->toBe([2, 2, 2]);
});
