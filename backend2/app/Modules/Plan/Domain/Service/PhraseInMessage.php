<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Service;

/**
 * THE «GLUE + PRONOUN» RULE: a phrase is IN a learner message when the message differs from it
 * only by leading conversational glue («Yes,», «Okay,») and by one pro-form standing for the thing
 * the phrase names («take it» for «take the medicine», «don't have either» for «don't have a
 * fever»). Anything else means the phrase is not in that message — the prompt's own rule, checked
 * in code so a stray `phrase_id` is a fact and not an opinion.
 */
final class PhraseInMessage
{
    private const GLUE = ['yes', 'no', 'okay', 'ok', 'well', 'so', 'sure', 'alright', 'right', 'oh', 'thanks', 'thank'];

    private const PRO_FORMS = ['it', 'that', 'this', 'one', 'ones', 'either', 'neither', 'there', 'them', 'those', 'these', 'here'];

    public static function matches(string $phrase, string $message): bool
    {
        $wanted = Words::tokens($phrase);
        $said = self::withoutGlue(Words::tokens($message));
        if ($wanted === [] || $said === []) {
            return false;
        }
        if ($wanted === $said) {
            return true;
        }

        // One pro-form in the message stands for a span of one to four words in the phrase.
        $prefix = 0;
        while ($prefix < count($wanted) && $prefix < count($said) && $wanted[$prefix] === $said[$prefix]) {
            $prefix++;
        }
        $suffix = 0;
        while ($suffix < count($wanted) - $prefix && $suffix < count($said) - $prefix
            && $wanted[count($wanted) - 1 - $suffix] === $said[count($said) - 1 - $suffix]) {
            $suffix++;
        }

        $saidMiddle = array_slice($said, $prefix, count($said) - $prefix - $suffix);
        $wantedMiddle = array_slice($wanted, $prefix, count($wanted) - $prefix - $suffix);

        return count($saidMiddle) === 1
            && in_array($saidMiddle[0], self::PRO_FORMS, true)
            && count($wantedMiddle) >= 1
            && count($wantedMiddle) <= 4;
    }

    /**
     * @param  list<string>  $tokens
     * @return list<string>
     */
    private static function withoutGlue(array $tokens): array
    {
        while ($tokens !== [] && in_array($tokens[0], self::GLUE, true)) {
            array_shift($tokens);
        }

        return $tokens;
    }
}
