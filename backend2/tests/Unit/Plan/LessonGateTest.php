<?php

declare(strict_types=1);

use App\Modules\Plan\Domain\Check\LessonCodes;
use App\Modules\Plan\Domain\Check\LessonGate;
use App\Modules\Plan\Domain\Check\LessonViolation;
use App\Modules\Plan\Domain\Lesson\LessonCard;

/**
 * WHAT HOLDS A DAY BACK (решения архитектора после GEN-2a и в GEN-2b, docs/plan-v2.md §4): seven fatal codes, the cards
 * a repair takes for them in the order it reaches furthest, and the reason a day fails with.
 */

// Canon GEN-2b: «фатальные — ТЕ ЖЕ пять из GEN-2a плюс exchange.second_question и exchange.repeats. Ничего нового
// фатальным не делать». Catches a code made fatal that is not on the list — a heuristic warning holding a learner's day
// for a paid repair — and one of the seven left out, dealing a broken card.
it('holds the day for exactly the seven fatal codes, and everything else the validator counts is a warning', function () {
    $fatal = [
        LessonCodes::LINE_NE_FRAME, LessonCodes::FILLER_UNGRAMMATICAL, LessonCodes::CHECK_SHAPE, LessonCodes::LISTENING_SHAPE,
        LessonCodes::EXCHANGE_SHAPE, LessonCodes::EXCHANGE_SECOND_QUESTION, LessonCodes::EXCHANGE_REPEATS,
    ];

    expect(array_values(array_filter(LessonCodes::all(), LessonGate::isFatal(...))))->toEqualCanonicalizing($fatal)
        ->and(count(LessonGate::FATAL))->toBe(7)
        ->and(array_values(array_diff(LessonGate::FATAL, LessonCodes::all())))->toBe([])
        ->and(LessonGate::isFatal(LessonCodes::LANG_PACK_MISSING))->toBeFalse()
        ->and(LessonGate::isFatal(LessonCodes::FILLER_NATIVE_SEAM))->toBeFalse()
        ->and(LessonGate::MAX_CARDS)->toBe(2);
});

// Catches a gate that repairs lines before the frame they are assembled from, or a line and a check of an exchange before
// the exchange that brings both back — spending both cards where one would do — and a fatal code of an exchange that
// stands at no card (v4.4 failed such a day without a repair; P2R v1.1 takes the whole exchange).
it('asks for a frame, then a whole exchange, then lines, checks and listening — each card once', function () {
    $found = [
        new LessonViolation(LessonCodes::LINE_NE_FRAME, 'B10', 'x'),
        new LessonViolation(LessonCodes::CHECK_SHAPE, 'x3.check', 'x'),
        new LessonViolation(LessonCodes::EXCHANGE_REPEATS, 'x8', 'x'),
        new LessonViolation(LessonCodes::LINE_NE_FRAME, 'B2', 'x'),
        new LessonViolation(LessonCodes::VARIANT_LONGER, 'B2', 'x'),
        new LessonViolation(LessonCodes::FILLER_UNGRAMMATICAL, 'p1.f2', 'x'),
        new LessonViolation(LessonCodes::EXCHANGE_SECOND_QUESTION, 'x4', 'x'),
        new LessonViolation(LessonCodes::FILLER_UNGRAMMATICAL, 'p1.f3', 'x'),
        new LessonViolation(LessonCodes::LISTENING_SHAPE, 'L2', 'x'),
        new LessonViolation(LessonCodes::EXCHANGE_SHAPE, 'x4', 'x'),
    ];
    $fatal = LessonGate::fatal($found);
    $cards = LessonGate::cards($fatal) ?? [];

    expect(array_map(static fn (LessonCard $c): string => $c->address, $cards))->toBe(['p1', 'x4', 'x8', 'B2', 'B10', 'x3.check', 'L2'])
        ->and(array_map(static fn (LessonCard $c): string => $c->kind, $cards))->toBe(['frame', 'exchange', 'exchange', 'line', 'line', 'check', 'listening'])
        ->and(LessonGate::cards([...$fatal, new LessonViolation(LessonCodes::EXCHANGE_SHAPE, 'lesson', 'x')]))->toBeNull()
        ->and(LessonGate::failReason($fatal))->toBe('fatal: line.ne_frame, check.shape, exchange.repeats, filler.ungrammatical, exchange.second_question, listening.shape, exchange.shape');
});
