<?php

declare(strict_types=1);

use App\Modules\Learning\Domain\Service\LearningLadder;
use App\Modules\Learning\Domain\Service\SessionLayout;
use App\Modules\Learning\Domain\ValueObject\SessionSlot;

beforeEach(function () {
    $this->layout = new SessionLayout();
});

/**
 * @param  list<string>  $terms
 * @return list<array{term_id: string, step: int}>
 */
function introducing(array $terms): array
{
    return array_map(static fn (string $t): array => ['term_id' => $t, 'step' => LearningLadder::STEP_INTRO], $terms);
}

/** @return list<string> "term:step" per position, which is what the assertions are actually about. */
function readable(array $slots): array
{
    return array_map(
        static fn (SessionSlot $s): string => $s->termId . ':' . ($s->ladderStep ?? 'due'),
        $slots,
    );
}

/** Positions of a term's cards, in order. @return list<int> */
function positionsOf(array $slots, string $termId): array
{
    $out = [];
    foreach ($slots as $index => $slot) {
        if ($slot->termId === $termId) {
            $out[] = $index;
        }
    }

    return $out;
}

it('brings every introduced word back TWICE in the same session, at widening gaps', function () {
    // Enough repeats that no slot goes unfilled — the gaps are then exactly as laid out. (A session
    // that runs out of material closes its tail gaps instead; that is the case below.)
    $repeats = array_map(static fn (int $i): string => "r{$i}", range(1, 16));
    $slots = $this->layout->arrange(introducing(['a', 'b', 'c', 'd']), $repeats, 20);

    foreach (['a', 'b', 'c', 'd'] as $term) {
        $positions = positionsOf($slots, $term);
        expect($positions)->toHaveCount(3);
        [$intro, $first, $second] = $positions;

        expect($first - $intro)->toBeGreaterThanOrEqual(SessionLayout::FIRST_GAP_MIN)
            ->and($first - $intro)->toBeLessThanOrEqual(SessionLayout::FIRST_GAP_MAX)
            ->and($second - $first)->toBeGreaterThanOrEqual(SessionLayout::SECOND_GAP_MIN)
            ->and($second - $first)->toBeLessThanOrEqual(SessionLayout::SECOND_GAP_MAX);

        // …and the cards are the three rungs, in order.
        expect(array_map(static fn (int $i): ?int => $slots[$i]->ladderStep, $positions))->toBe([0, 1, 2]);
    }
});

it('closes the gaps when there is genuinely nothing to put in them', function () {
    // One word, nothing else in the session. A gap exists to interpose OTHER cards; with none to
    // interpose, holding the slots open would end the session in dead air rather than in spacing.
    $slots = $this->layout->arrange(introducing(['a']), [], 20);

    expect(readable($slots))->toBe(['a:0', 'a:1', 'a:2']);
});

it('dilutes an empty first session with the ladder itself — no due cards needed', function () {
    // Twenty slots, nothing but brand-new words. The spec's case: the ladder has to break up its
    // own intros, because there is nothing else in the session to do it.
    $slots = $this->layout->arrange(introducing(['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h']), [], 20);

    expect(readable($slots))->toBe([
        'a:0', 'b:0', 'a:1', 'b:1',
        'c:0', 'd:0', 'a:2', 'b:2', 'c:1', 'd:1',
        'e:0', 'f:0', 'c:2', 'd:2', 'e:1', 'f:1',
        'e:2', 'f:2',
    ]);
});

it('never lets intros run more than two deep', function () {
    $slots = $this->layout->arrange(introducing(['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h']), [], 20);

    $run = 0;
    foreach ($slots as $slot) {
        $run = $slot->ladderStep === LearningLadder::STEP_INTRO ? $run + 1 : 0;
        expect($run)->toBeLessThanOrEqual(SessionLayout::MAX_CONSECUTIVE_INTROS);
    }
});

it('interleaves ladder cards with due repeats instead of stapling two sessions together', function () {
    $slots = $this->layout->arrange(introducing(['a', 'b']), ['r1', 'r2', 'r3', 'r4'], 12);

    // Alternating while both queues have something: a new word, a repeat, its own follow-up…
    expect(readable($slots))->toBe([
        'a:0', 'r1:due', 'a:1', 'b:0', 'r2:due', 'b:1',
        'a:2', 'r3:due', 'r4:due', 'b:2',
    ]);
});

it('defers a whole word rather than introducing one whose recognitions fall off the end', function () {
    // Seven slots is room for exactly one chain (0, 2, 6): a word met and then not asked until
    // tomorrow is worse than a word not met, so the second one waits for the next session, whole.
    $slots = $this->layout->arrange(introducing(['a', 'b']), [], 7);

    expect(positionsOf($slots, 'a'))->toHaveCount(3)
        ->and(positionsOf($slots, 'b'))->toBe([]);
});

it('fills what is left with due repeats when no more words fit', function () {
    $slots = $this->layout->arrange(introducing(['a', 'b']), ['r1', 'r2'], 7);

    // 'b' cannot fit its chain, so it waits — but its slots go to repeats, not to nothing.
    expect(positionsOf($slots, 'b'))->toBe([]);
    expect(readable($slots))->toContain('r1:due')->toContain('r2:due');
});

it('starts a word already partway up the ladder at ITS rung, not at the intro', function () {
    // «Не уверен» in triage, or a session abandoned after the intro: the chain is shorter, and the
    // card it opens with is the one the pair is actually owed.
    $slots = $this->layout->arrange([
        ['term_id' => 'a', 'step' => LearningLadder::STEP_RECOGNITION_FORWARD],
        ['term_id' => 'b', 'step' => LearningLadder::STEP_RECOGNITION_REVERSE],
    ], [], 20);

    expect(array_map(static fn (int $i): ?int => $slots[$i]->ladderStep, positionsOf($slots, 'a')))->toBe([1, 2])
        ->and(array_map(static fn (int $i): ?int => $slots[$i]->ladderStep, positionsOf($slots, 'b')))->toBe([2]);
});

it('lays a chain out in ASCENDING rung order, one rung at a time', function () {
    // The layout is a PLAN made before the first answer, and the device resolves it at deal time: a
    // recognition slot is played at the rung the pair actually stands on, so a FAILED rung is
    // replayed instead of the next one being dealt (QA-9, mobile RecognitionReplay). That resolution
    // reaches BACKWARDS for the term's card at the lower rung, which only works because a chain is
    // laid out in ascending order and never skips a rung — pinned here, on the side that lays it out,
    // and mirrored by the client's own test over the ported algorithm.
    $slots = $this->layout->arrange([
        ...introducing(['a', 'b']),
        ['term_id' => 'c', 'step' => LearningLadder::STEP_RECOGNITION_FORWARD],
    ], ['r1', 'r2', 'r3', 'r4'], 20);

    foreach (['a', 'b', 'c'] as $term) {
        $steps = array_map(static fn (int $i): ?int => $slots[$i]->ladderStep, positionsOf($slots, $term));
        expect($steps)->not->toBe([]);
        foreach ($steps as $index => $step) {
            if ($index > 0) {
                expect($step)->toBe($steps[$index - 1] + 1, "{$term} must climb one rung at a time, in order");
            }
        }
    }
});

it('is deterministic — the same inputs always deal the same order', function () {
    // A retried build must not re-deal a session the learner is halfway through.
    $first = readable($this->layout->arrange(introducing(['a', 'b', 'c']), ['r1', 'r2'], 15));
    $second = readable($this->layout->arrange(introducing(['a', 'b', 'c']), ['r1', 'r2'], 15));

    expect($first)->toBe($second);
});

it('never overruns the session size, and never repeats a slot', function () {
    $slots = $this->layout->arrange(introducing(['a', 'b', 'c', 'd', 'e']), ['r1', 'r2', 'r3'], 12);

    expect(count($slots))->toBeLessThanOrEqual(12)
        ->and(readable($slots))->toBe(array_values(array_unique(readable($slots))));
});

it('returns nothing for a session with no room', function () {
    expect($this->layout->arrange(introducing(['a']), ['r1'], 0))->toBe([]);
});
