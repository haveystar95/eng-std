<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Service;

use App\Modules\Plan\Domain\Entity\DayCard;
use App\Modules\Plan\Domain\ValueObject\CardResult;
use App\Modules\Plan\Domain\ValueObject\ConversationOutcome;
use App\Modules\Plan\Domain\ValueObject\Stage;

/**
 * «ЧТО БЫЛО ХОРОШО» — two or three lines on the day's summary (кадр 37-13, наряд CONV-1, п. 3).
 *
 * The server writes the words and the client prints them: the lines inflect («6 реплик», «5 фраз»,
 * «кроме одного вопроса»), and inflection is the server's job in this product ({@see NativeStrings}).
 *
 * They are read off the day itself, not kept anywhere: what the learner said aloud across the day's
 * spoken cards, what the talk used of the plan's phrases, and whether the role's questions landed.
 * A day without a talk keeps the first line and nothing else — a block that says «0 фраз» about a
 * conversation that never happened is not a highlight, it is an accusation.
 */
final class DayHighlights
{
    /** At most this many lines — the frame fits three (кадр 37-13). */
    public const MAX = 3;

    /**
     * @param  list<DayCard>  $cards
     * @return list<string> ready to print, in the order the frame stacks them
     */
    public static function of(array $cards, ?ConversationOutcome $talk, NativeStrings $strings): array
    {
        $out = [];

        $spoken = array_values(array_filter($cards, static fn (DayCard $c): bool => self::isSpoken($c)));
        $total = count($spoken);
        if ($total > 0) {
            $passed = count(array_filter(
                $spoken,
                static fn (DayCard $c): bool => in_array($c->result(), [CardResult::Passed, CardResult::Hinted], true),
            ));
            $out[] = $strings->highlight('said_self', $passed, 'line_of', $total);
        }

        if ($talk === null) {
            return $out;
        }

        if ($talk->phrasesTotal > 0) {
            $out[] = $strings->highlight('phrases_used', $talk->phrasesUsedCount(), 'phrase_of', $talk->phrasesTotal);
        }
        $out[] = $talk->understoodAll
            ? $strings->highlight('understood_all', 0, 'question_of')
            : $strings->highlight('understood_except', $talk->notUnderstood, 'question_of');

        return array_slice($out, 0, self::MAX);
    }

    /** A card the learner SAID: the three kinds of «Говорю сам», the rehearsal's «Вспомнить», the review's «Повторение». */
    private static function isSpoken(DayCard $card): bool
    {
        return in_array($card->stage(), Stage::spoken(), true)
            && ($card->kind()->isSpoken() || $card->kind()->isJudged());
    }
}
