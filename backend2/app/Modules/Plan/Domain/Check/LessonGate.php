<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check;

use App\Modules\Plan\Domain\Lesson\LessonCard;

/**
 * WHAT HOLDS A DAY BACK (решения архитектора после GEN-2a, в наряде GEN-2b и в GEN-3, `docs/plan-v2.md` §4): nine codes are
 * fatal — the learner would get a broken card: a line served otherwise than the model wrote it, a frame whose filler
 * makes it ungrammatical, a check or a listening question that cannot be dealt, an exchange said by the wrong
 * speakers, an exchange whose closing message asks, an exchange that says a frame with a filler another exchange
 * already said — or material the learner already learned on an earlier day of the plan: a word, a frame said the same
 * way. An abbreviation as a word of the day is not among them (доработка GEN-3): it is a word when the learner's language
 * has an everyday one for it, and that is the model's to judge. A lesson with any of them is not dealt until P2R repairs
 * the card at their address — at most two cards a day; a
 * fatal finding left after that, or one that stands at no card a repair can take, fails the day with its code. Nothing
 * else is fatal.
 *
 * The cards go in the order a repair reaches furthest: a frame first (its lines are assembled from it, so a
 * repaired frame may put them right), then a whole exchange (its lines and its check come back with it), then
 * learner lines, checks, listening questions, and a word last — a word changes nothing else of the day.
 */
final class LessonGate
{
    public const FATAL = [
        LessonCodes::LINE_NE_FRAME,
        LessonCodes::FILLER_UNGRAMMATICAL,
        LessonCodes::CHECK_SHAPE,
        LessonCodes::LISTENING_SHAPE,
        LessonCodes::EXCHANGE_SHAPE,
        LessonCodes::EXCHANGE_SECOND_QUESTION,
        LessonCodes::EXCHANGE_REPEATS,
        LessonCodes::VOCAB_KNOWN_REPEAT,
        LessonCodes::FRAME_KNOWN_REPEAT,
    ];

    public const MAX_CARDS = 2;

    private const ORDER = [LessonCard::FRAME => 0, LessonCard::EXCHANGE => 1, LessonCard::LINE => 2, LessonCard::CHECK => 3, LessonCard::LISTENING => 4, LessonCard::TERM => 5];

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
