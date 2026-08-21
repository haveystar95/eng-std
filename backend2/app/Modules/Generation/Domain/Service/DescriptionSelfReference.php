<?php

declare(strict_types=1);

namespace App\Modules\Generation\Domain\Service;

/**
 * Does a description give away the word it describes?
 *
 * The `description_match` trainer shows the description and asks WHICH WORD it is about. A
 * description containing its own headword answers that card before it is asked, and it is the
 * single most likely way for a model to ruin this content — «A bank is a place where you keep
 * money» is a perfectly good dictionary entry and a completely useless card.
 *
 * Word-boundary matching, so the check catches the word and not every string that happens to hold
 * its letters: describing `cat` must not trip on «catalogue», and describing `ill` must not trip on
 * «will». For a multi-word term the whole phrase is looked for as a unit, since that is what would
 * be given away.
 *
 * A crude suffix family is checked alongside the bare form — plural, verb forms, comparative — for
 * the obvious dodge of using the same word in another shape. It is deliberately crude: an
 * irregular form («go» → «went») slips through, and that is the honest limit of a rule that must
 * not have a morphology engine behind it. It is a floor, like {@see \App\Modules\Shared\Domain\Service\LanguagePurity},
 * and the prompt's own rule is what covers the rest.
 */
final class DescriptionSelfReference
{
    /** Suffixes tried on top of the bare term. `-e` words are handled by trimming it first. */
    private const SUFFIXES = ['s', 'es', 'ed', 'd', 'ing', 'er', 'est', 'ly'];

    public static function givesAway(string $description, string $term): bool
    {
        $needle = trim($term);
        if ($needle === '' || trim($description) === '') {
            return false;
        }

        foreach (self::forms($needle) as $form) {
            if (self::containsWord($description, $form)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The bare term plus its crude suffix family. A multi-word phrase is only ever looked for whole:
     * inflecting a phrase this way produces nonsense, and its individual words legitimately appear
     * in a description of it.
     *
     * @return list<string>
     */
    private static function forms(string $term): array
    {
        $forms = [$term];
        if (str_contains($term, ' ') || str_contains($term, '-')) {
            return $forms;
        }

        // «love» → «loves», «loved», «loving»: the stem for -ing/-ed drops a final silent e.
        $stem = preg_match('/e$/ui', $term) === 1 ? mb_substr($term, 0, -1) : $term;
        foreach (self::SUFFIXES as $suffix) {
            $forms[] = $term . $suffix;
            if ($stem !== $term) {
                $forms[] = $stem . $suffix;
            }
        }

        return array_values(array_unique($forms));
    }

    /** Case-insensitive, on word boundaries — so «cat» does not match inside «catalogue». */
    private static function containsWord(string $haystack, string $needle): bool
    {
        $pattern = '/(?<![\p{L}\p{N}])' . preg_quote($needle, '/') . '(?![\p{L}\p{N}])/ui';

        return preg_match($pattern, $haystack) === 1;
    }
}
