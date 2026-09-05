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
 * ДЕНЬ = ОДИН ПРИСЕСТ, ПРОГОН — ВТОРОЙ (наряд DAY-FIX-2, Ч.2.1).
 *
 * The minutes used to cut the day on section boundaries; now the day is one sitting whatever the
 * minutes, and the only second sitting is the прогон сцены — a different act, with the microphone
 * and nothing on the screen.
 */
it('is one sitting for a day with no прогон in it', function () {
    $day = [...part(S::WARMUP, 5), ...part(S::WORDS . '#1', 6), ...part(S::DIALOGUE_INTRO . '#1', 12), ...part(S::DIALOGUE . '#1', 12)];

    expect(PlanSittings::split($day))->toBe([35]);
});

it('puts the прогон into a sitting of its own, after the day', function () {
    $day = [...part(S::WARMUP, 5), ...part(S::DIALOGUE . '#2', 10), ...part(S::REVIEW . '#1', 6), ...part(S::SCENE_RUN . '#1', 6)];

    expect(PlanSittings::split($day))->toBe([21, 6]);
});

it('adds up to the whole day, so nothing the learner passed can burn', function () {
    $day = [...part(S::WARMUP, 7), ...part(S::WORDS . '#1', 11), ...part(S::SCENE_RUN . '#1', 4)];

    $sittings = PlanSittings::split($day);
    expect(array_sum($sittings))->toBe(count($day))
        ->and(array_filter($sittings, static fn (int $n): bool => $n <= 0))->toBe([]);
});

it('answers an empty day with no sittings at all', function () {
    expect(PlanSittings::split([]))->toBe([]);
});

it('is a single прогон sitting on the final day, which has no day part', function () {
    expect(PlanSittings::split([...part(S::SCENE_RUN . '#1', 5), ...part(S::SCENE_RUN . '#2', 4)]))->toBe([9]);
});

it('states the owner`s ceiling — forty — as the domain`s own number', function () {
    expect(PlanSittings::MAX_TASKS_PER_SITTING)->toBe(40);
});
