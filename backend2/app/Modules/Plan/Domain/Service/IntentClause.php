<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Service;

/**
 * THE INTENTION AS A CLAUSE (наряд CONV-2, п. 11) — what `hints.native` is on the wire: the part after «Скажи, что …»,
 * which the client prints around it (кадр 37-7).
 *
 * The hint is the learner's own line in their language, and a line is a sentence: «У моего сына температура.» Glued
 * into the chip as it stands it read «Скажи, что У моего сына температура.» on the phone, and the client wrote its own
 * correction (`TalkTexts.clause`, CLIENT-CONV-1a §5 п. 10). The server sends the clause instead, by the same rule, so
 * the client's correction can go: the first letter lowered only when the second is lower-case already — an
 * abbreviation keeps its capitals («США …» stays), one closing full stop dropped, and a question mark, an exclamation
 * mark, an ellipsis and a closing quote kept — they are part of what is to be said. Idempotent: a clause stays itself.
 */
final class IntentClause
{
    public static function of(string $sentence): string
    {
        $text = trim($sentence);
        if (str_ends_with($text, '.') && ! str_ends_with($text, '..')) {
            $text = rtrim(mb_substr($text, 0, -1));
        }
        if (mb_strlen($text) < 2) {
            return $text;
        }
        $first = mb_substr($text, 0, 1);
        $second = mb_substr($text, 1, 1);
        $isCapital = mb_strtoupper($first) === $first && mb_strtolower($first) !== $first;
        if ($isCapital && mb_strtolower($second) === $second) {
            $text = mb_strtolower($first).mb_substr($text, 1);
        }

        return $text;
    }
}
