<?php

declare(strict_types=1);

use App\Modules\Learning\Domain\Service\PlanSessionSections as S;
use App\Modules\Learning\Domain\Service\PlanSittings;

/** `n` tasks of one section, as the flat list the cutter reads. */
function part(string $section, int $count): array
{
    return array_fill(0, $count, $section);
}

/**
 * ДВА ПРИСЕСТА — «МАТЕРИАЛ» И «РАЗГОВОР» (наряд DAY-FIX-3, Ч.4).
 *
 * The cut fell between the day and the прогон (DAY-FIX-2); now it falls between what the learner
 * meets and exercises and what they say: the dialogue, the seam's lines and the прогон are one
 * sitting, everything before them another.
 */
it('cuts a day into the material and the conversation', function () {
    $day = [...part(S::WARMUP, 5), ...part(S::WORDS . '#1', 12), ...part(S::DIALOGUE_INTRO . '#1', 15), ...part(S::DIALOGUE . '#1', 10)];

    expect(PlanSittings::split($day))->toBe([32, 10])
        ->and(PlanSittings::plan($day))->toBe([
            ['kind' => PlanSittings::MATERIAL, 'cards' => 32],
            ['kind' => PlanSittings::CONVERSATION, 'cards' => 10],
        ]);
});

it('puts the seam’s lines and the прогон into the conversation, the seam’s words into the material', function () {
    $day = [...part(S::WARMUP, 5), ...part(S::WORDS . '#2', 8), ...part(S::WORDS . '#1', 6), ...part(S::DIALOGUE . '#2', 10), ...part(S::DIALOGUE . '#1', 5), ...part(S::SCENE_RUN . '#1', 6)];

    expect(PlanSittings::split($day))->toBe([19, 21]);
});

it('adds up to the whole day, so nothing the learner passed can burn', function () {
    $day = [...part(S::WARMUP, 7), ...part(S::WORDS . '#1', 11), ...part(S::SCENE_RUN . '#1', 4)];

    $sittings = PlanSittings::split($day);
    expect(array_sum($sittings))->toBe(count($day))
        ->and(array_filter($sittings, static fn (int $n): bool => $n <= 0))->toBe([]);
});

it('answers an empty day with no sittings at all', function () {
    expect(PlanSittings::split([]))->toBe([])
        ->and(PlanSittings::plan([]))->toBe([]);
});

it('is a single conversation sitting on the final day, which has no material', function () {
    $day = [...part(S::SCENE_RUN . '#1', 5), ...part(S::REHEARSAL . '#2', 4)];

    expect(PlanSittings::split($day))->toBe([9])
        ->and(PlanSittings::plan($day))->toBe([['kind' => PlanSittings::CONVERSATION, 'cards' => 9]]);
});

it('is a single material sitting for a day of introductions with no conversation yet', function () {
    expect(PlanSittings::plan([...part(S::WARMUP, 3), ...part(S::WORDS . '#1', 6)]))
        ->toBe([['kind' => PlanSittings::MATERIAL, 'cards' => 9]]);
});

it('names the sitting of every section the planner keys', function () {
    foreach ([S::WARMUP, S::WORDS . '#1', S::DIALOGUE_INTRO . '#1', S::NUMBERS . '#1', S::REVIEW . '#1', S::DAY . '#1'] as $section) {
        expect(PlanSittings::kindOf($section))->toBe(PlanSittings::MATERIAL, $section);
    }
    foreach ([S::DIALOGUE . '#1', S::SCENE_RUN . '#1', S::REHEARSAL . '#1'] as $section) {
        expect(PlanSittings::kindOf($section))->toBe(PlanSittings::CONVERSATION, $section);
    }
});

it('states the owner`s ceilings — forty-five and twenty-five — as the domain`s own numbers', function () {
    expect(PlanSittings::MATERIAL_MAX_CARDS)->toBe(45)
        ->and(PlanSittings::CONVERSATION_MAX_CARDS)->toBe(25);
});
