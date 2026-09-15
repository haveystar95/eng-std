<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check;

use App\Modules\Plan\Domain\Lesson\LessonCard;

/**
 * WHAT HOLDS A DAY BACK (решение архитектора после GEN-2a, `docs/plan-v2.md` §4): five codes are fatal —
 * the learner would get a broken card: a line served otherwise than the model wrote it, a frame whose
 * filler makes it ungrammatical, a check or a listening question that cannot be dealt, an exchange said
 * by the wrong speakers. A lesson with any of them is not dealt until P2R repairs the card at their
 * address — at most two cards a day; a fatal finding left after that, or one that stands at no card a
 * repair can take (an exchange's shape is the whole exchange), fails the day with its code.
 *
 * The cards go in the order a repair reaches furthest: a frame first (its lines are assembled from it, so
 * a repaired frame may put them right), then learner lines, checks, listening questions.
 */
final class LessonGate
{
    public const FATAL = [
        LessonCodes::LINE_NE_FRAME,
        LessonCodes::FILLER_UNGRAMMATICAL,
        LessonCodes::CHECK_SHAPE,
        LessonCodes::LISTENING_SHAPE,
        LessonCodes::EXCHANGE_SHAPE,
    ];

    public const MAX_CARDS = 2;

    private const ORDER = [LessonCard::FRAME => 0, LessonCard::LINE => 1, LessonCard::CHECK => 2, LessonCard::LISTENING => 3];

    public static function isFatal(string $code): bool
    {
        return in_array($code, self::FATAL, true);
    }

    /**
     * @param  list<LessonViolation>  $violations
     * @return list<LessonViolation>
     */
    public static function fatal(array $violations): array
    {
        return array_values(array_filter($violations, static fn (LessonViolation $v): bool => self::isFatal($v->code)));
    }

    /**
     * The cards the fatal findings stand at, each once, in repair order; null when one of them stands at no
     * card a repair can take — such a day cannot be put right by a repair.
     *
     * @param  list<LessonViolation>  $fatal
     * @return list<LessonCard>|null
     */
    public static function cards(array $fatal): ?array
    {
        $cards = [];
        foreach ($fatal as $violation) {
            $card = LessonCard::at($violation->address);
            if ($card === null) {
                return null;
            }
            $cards[$card->address] = $card;
        }
        $cards = array_values($cards);
        usort($cards, static fn (LessonCard $a, LessonCard $b): int => self::ORDER[$a->kind] <=> self::ORDER[$b->kind] ?: strnatcmp($a->address, $b->address));

        return $cards;
    }

    /**
     * Why the day failed: the fatal codes left, each once, in the order found — `fatal: line.ne_frame, check.shape`.
     *
     * @param  list<LessonViolation>  $fatal
     */
    public static function failReason(array $fatal): string
    {
        return 'fatal: '.implode(', ', array_values(array_unique(array_map(static fn (LessonViolation $v): string => $v->code, $fatal))));
    }
}
