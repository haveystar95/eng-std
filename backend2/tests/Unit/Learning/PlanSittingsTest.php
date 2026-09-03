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
        ...part(S::HEAR, 4),
        ...part(S::SAY, 8),
        ...part(S::ASK, 3),
    ];

    // Budget 12: warm-up (6) + words (9) would be 15, so the words open a second присест.
    expect(PlanSittings::cut($sections, 12))->toBe([6, 9, 12, 3]);
});

it('never splits a section, even when the section is longer than the whole budget', function () {
    $sections = [...part(S::WARMUP, 2), ...part(S::SAY, 20), ...part(S::ASK, 1)];

    // «Секцию не рвать; если секция длиннее бюджета — она и есть присест.»
    expect(PlanSittings::cut($sections, 5))->toBe([2, 20, 1]);
});

it('adds up to the whole day, so nothing the learner passed can burn', function () {
    $sections = [
        ...part(S::WARMUP, 7), ...part(S::WORDS, 11), ...part(S::HEAR, 5),
        ...part(S::SAY, 9), ...part(S::ASK, 4), ...part(S::REVIEW, 6),
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
    expect(PlanSittings::cut([...part(S::WARMUP, 2), ...part(S::SAY, 2)], 0))->toBe([2, 2]);
});

it('re-opens a section that comes back later as a section of its own', function () {
    // The cutter reads the list it is given and does not reorder it: two runs of the same key are
    // two parts, because a boundary is «the shelf changed», not «this shelf has appeared before».
    expect(PlanSittings::cut([...part(S::SAY, 2), ...part(S::HEAR, 2), ...part(S::SAY, 2)], 3))
        ->toBe([2, 2, 2]);
});
