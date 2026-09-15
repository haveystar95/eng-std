<?php

declare(strict_types=1);

use App\Modules\Plan\Domain\Check\LessonCodes;
use App\Modules\Plan\Domain\Check\LessonGate;
use App\Modules\Plan\Domain\Check\LessonViolation;
use App\Modules\Plan\Domain\Lesson\LessonCard;

/**
 * WHAT HOLDS A DAY BACK (решение архитектора после GEN-2a, docs/plan-v2.md §4): five fatal codes, the cards a repair
 * takes for them in the order it reaches furthest, and the reason a day fails with.
 */
it('holds the day for the five fatal codes and for nothing else the validator counts', function () {
    $fatal = [LessonCodes::LINE_NE_FRAME, LessonCodes::FILLER_UNGRAMMATICAL, LessonCodes::CHECK_SHAPE, LessonCodes::LISTENING_SHAPE, LessonCodes::EXCHANGE_SHAPE];

    expect(array_values(array_filter(LessonCodes::all(), LessonGate::isFatal(...))))->toEqualCanonicalizing($fatal)
        ->and(LessonGate::MAX_CARDS)->toBe(2);
});

// Catches a gate that repairs lines before the frame they are assembled from — spending both cards where one would
// do — and one that asks a repair for a finding no card can take.
it('asks for a frame before its lines, each card once, and for no card when a fatal finding stands at none', function () {
    $found = [
        new LessonViolation(LessonCodes::LINE_NE_FRAME, 'B10', 'x'),
        new LessonViolation(LessonCodes::CHECK_SHAPE, 'x3.check', 'x'),
        new LessonViolation(LessonCodes::LINE_NE_FRAME, 'B2', 'x'),
        new LessonViolation(LessonCodes::KEY_NO_CONTENT_WORD, 'B2', 'x'),
        new LessonViolation(LessonCodes::FILLER_UNGRAMMATICAL, 'p1.f2', 'x'),
        new LessonViolation(LessonCodes::FILLER_UNGRAMMATICAL, 'p1.f3', 'x'),
        new LessonViolation(LessonCodes::LISTENING_SHAPE, 'L2', 'x'),
    ];
    $fatal = LessonGate::fatal($found);

    expect(array_map(static fn (LessonCard $c): string => $c->address, LessonGate::cards($fatal) ?? []))->toBe(['p1', 'B2', 'B10', 'x3.check', 'L2'])
        ->and(LessonGate::cards([...$fatal, new LessonViolation(LessonCodes::EXCHANGE_SHAPE, 'x4', 'x')]))->toBeNull()
        ->and(LessonGate::failReason($fatal))->toBe('fatal: line.ne_frame, check.shape, filler.ungrammatical, listening.shape');
});
