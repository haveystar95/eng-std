<?php

declare(strict_types=1);

use App\Modules\Plan\Domain\Check\LessonCodes;
use App\Modules\Plan\Domain\Check\LessonGate;
use App\Modules\Plan\Domain\Check\LessonViolation;
use App\Modules\Plan\Domain\Lesson\LessonCard;

/**
 * WHAT HOLDS A DAY BACK (решения архитектора после GEN-2a, в GEN-2b и в GEN-3, docs/plan-v2.md §4): nine fatal codes, the
 * cards a repair takes for them in the order it reaches furthest, and the reason a day fails with.
 */

// Canon GEN-2b: «фатальные — ТЕ ЖЕ пять из GEN-2a плюс exchange.second_question и exchange.repeats»; наряд GEN-3: «фатально
// — vocab.known_repeat и frame.known_repeat; frame.known_native_repeat, frame.twin, frame.adjacent_repeat, role_gender.changed
// — предупреждения»; доработка GEN-3: «vocab.abbreviation — из фатальных в предупреждения (фатальных снова 9): аббревиатура
// допустима словом дня, если в NATIVE_LANGUAGE есть обычное слово; судит модель, код только считает»; «frame.known_native_repeat
// — остаётся предупреждением». Catches a code made fatal that is not on the list — a heuristic warning holding a learner's day
// for a paid repair: an ATM taught as «банкомат» held back as an acronym, a native frame translated the way day 1 translated
// one — and one of the nine left out, dealing a broken card or a word taught twice.
it('holds the day for exactly the nine fatal codes, and everything else the validator counts is a warning', function () {
    $fatal = [
        LessonCodes::LINE_NE_FRAME, LessonCodes::FILLER_UNGRAMMATICAL, LessonCodes::CHECK_SHAPE, LessonCodes::LISTENING_SHAPE,
        LessonCodes::EXCHANGE_SHAPE, LessonCodes::EXCHANGE_SECOND_QUESTION, LessonCodes::EXCHANGE_REPEATS,
        LessonCodes::VOCAB_KNOWN_REPEAT, LessonCodes::FRAME_KNOWN_REPEAT,
    ];

    expect(array_values(array_filter(LessonCodes::all(), LessonGate::isFatal(...))))->toEqualCanonicalizing($fatal)
        ->and(count(LessonGate::FATAL))->toBe(9)
        ->and(array_filter(
            [LessonCodes::FRAME_KNOWN_NATIVE_REPEAT, LessonCodes::FRAME_TWIN, LessonCodes::FRAME_ADJACENT_REPEAT, LessonCodes::ROLE_GENDER_CHANGED, LessonCodes::VOCAB_ABBREVIATION],
            LessonGate::isFatal(...),
        ))->toBe([])
        ->and(array_values(array_diff(LessonGate::FATAL, LessonCodes::all())))->toBe([])
        ->and(LessonGate::isFatal(LessonCodes::LANG_PACK_MISSING))->toBeFalse()
        ->and(LessonGate::isFatal(LessonCodes::FILLER_NATIVE_SEAM))->toBeFalse()
        ->and(LessonGate::MAX_CARDS)->toBe(2);
});

// Catches a gate that repairs lines before the frame they are assembled from, or a line and a check of an exchange before
// the exchange that brings both back — spending both cards where one would do — and a fatal code of an exchange that
// stands at no card (v4.4 failed such a day without a repair; P2R v1.1 takes the whole exchange). Наряд GEN-3: a word an
// earlier day taught is a card of its own (P2R v1.2, `term`), repaired last — it changes nothing else of the day.
it('asks for a frame, then a whole exchange, then lines, checks, listening and words — each card once', function () {
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
        new LessonViolation(LessonCodes::VOCAB_KNOWN_REPEAT, 'v3', 'x'),
        new LessonViolation(LessonCodes::FRAME_KNOWN_REPEAT, 'p2', 'x'),
    ];
    $fatal = LessonGate::fatal($found);
    $cards = LessonGate::cards($fatal) ?? [];

    expect(array_map(static fn (LessonCard $c): string => $c->address, $cards))->toBe(['p1', 'p2', 'x4', 'x8', 'B2', 'B10', 'x3.check', 'L2', 'v3'])
        ->and(array_map(static fn (LessonCard $c): string => $c->kind, $cards))->toBe(['frame', 'frame', 'exchange', 'exchange', 'line', 'line', 'check', 'listening', 'term'])
        ->and(LessonGate::cards([...$fatal, new LessonViolation(LessonCodes::EXCHANGE_SHAPE, 'lesson', 'x')]))->toBeNull()
        ->and(LessonGate::failReason($fatal))->toBe('fatal: line.ne_frame, check.shape, exchange.repeats, filler.ungrammatical, exchange.second_question, listening.shape, exchange.shape, vocab.known_repeat, frame.known_repeat');
});
