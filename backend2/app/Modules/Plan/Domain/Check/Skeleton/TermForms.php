<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Skeleton;

use App\Modules\Plan\Domain\Check\Language\LanguagePack;
use App\Modules\Plan\Domain\Check\Language\LanguageWords;
use App\Modules\Plan\Domain\Service\Words;

/**
 * IS A WORD OF THE DAY IN A TEXT — BY ITS FORM (наряд GEN-4: «по форме, лемма против спрягаемой формы»). The vocabulary is
 * written in its dictionary form («a candida», «a se ocupa de»), the frames and lines say it inflected («Candidez», «Mă
 * ocupam de»): a word is in a text when
 *
 *  - the text says the whole term, word for word ({@see Words::containsTerm()}); or
 *  - every CONTENT word of the term — a word that is no function word of the target's pack («a», «se», «de» are not);
 *    every word of it, when all are function words to the pack («vouloir», «können») — stands in the text in some form
 *    of it: the same word, one stem by the pack ({@see LanguageWords::sameStem()}), a
 *    conjugation of the same lemma (they share their first three letters and differ in no more than the last three of the
 *    shorter: «pagar» — «pago», «lucra» — «lucrat»), a word the term holds whole past a prefix («ausfüllen» — «fülle»), or
 *    a form the pack lists as irregular ({@see LanguageWords::irregular()}: «vouloir» — «veux», «a putea» — «pot»).
 *
 * A word of the text is read whole and in the parts an apostrophe or a hyphen joins: «l’ambiance» says «ambiance»,
 * «într-o» says «într» (the gate run of GEN-4 failed a day on «ambiance» in «l’ambiance»). No meaning beyond the pack's
 * list: an irregular form it does not list is not found — the rules that read this say so.
 */
final class TermForms
{
    /** `$words` null — a target nobody has written a pack for: only the whole term, word for word, is found. */
    public static function in(string $term, string $text, ?LanguageWords $words): bool
    {
        if (Words::containsTerm($term, $text)) {
            return true;
        }
        if ($words === null) {
            return false;
        }
        $all = Words::tokens($term);
        $content = array_values(array_filter($all, static fn (string $t): bool => ! $words->isFunction($t)));
        if ($content === []) {
            // A word the pack counts among its function words is still a word of the day when the vocabulary teaches it
            // («vouloir», «können» — the gate run failed a day on «vouloir» said as «Je veux»): every word of it is looked for.
            $content = $all;
        }
        if ($content === []) {
            return false;
        }
        $theirs = self::pieces(Words::tokens($text));
        foreach ($content as $word) {
            $found = false;
            foreach ($theirs as $other) {
                if (self::form($word, $other, $words)) {
                    $found = true;
                    break;
                }
            }
            if (! $found) {
                return false;
            }
        }

        return true;
    }

    /** Is `$form`, a word of the text, a form of `$lemma`, a word of the term? */
    public static function form(string $lemma, string $form, LanguageWords $words): bool
    {
        $a = LanguagePack::normal($lemma);
        $b = LanguagePack::normal($form);
        if ($a === $b || $words->sameStem($a, $b) || $words->irregular($a, $b)) {
            return true;
        }
        $shorter = min(mb_strlen($a), mb_strlen($b));
        $prefix = 0;
        while ($prefix < $shorter && mb_substr($a, $prefix, 1) === mb_substr($b, $prefix, 1)) {
            $prefix++;
        }
        if ($prefix >= 3 && $prefix >= $shorter - 3) {
            return true;
        }

        return mb_strlen($b) >= 5 && mb_strlen($a) > mb_strlen($b) && str_contains($a, mb_substr($b, 0, 4));
    }

    /**
     * The words of a text, each whole and — when an apostrophe or a hyphen joins it — in its parts too.
     *
     * @param  list<string>  $tokens
     * @return list<string>
     */
    private static function pieces(array $tokens): array
    {
        $out = $tokens;
        foreach ($tokens as $token) {
            if (preg_match("/['’-]/u", $token) === 1) {
                array_push($out, ...(preg_split("/['’-]/u", $token, -1, PREG_SPLIT_NO_EMPTY) ?: []));
            }
        }

        return array_values(array_unique($out));
    }
}
