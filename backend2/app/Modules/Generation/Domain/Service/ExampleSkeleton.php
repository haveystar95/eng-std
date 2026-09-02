<?php

declare(strict_types=1);

namespace App\Modules\Generation\Domain\Service;

/**
 * TWO EXAMPLES THAT ARE ONE SENTENCE WITH THE TERM SWAPPED — Д-29, and the defect that reaches the
 * learner as a graded exercise.
 *
 * The live day 3 of «К врачу с ребёнком» wrote six connectors and gave four of them the same
 * sentence with a different word dropped into the slot:
 *
 *     worse         «If the fever gets worse, I need to **worse** tomorrow.»
 *     come back     «What should I do if he gets **come back**?»
 *     this evening  «My child needs **this evening** twice a day.»
 *
 * Every existing gate passed them. They are not clones — no two strings are equal; they are not
 * lines of the day; each one contains its own term. What they are is ONE skeleton, filled four
 * times, and three of the four are not sentences a person would say. The learner met them as a
 * cloze card and a dictation, and the app marked «Верно» over nonsense.
 *
 * ## The skeleton, and why it is compared rather than the sentence
 *
 * Take the example, blank out every term of the day that stands in it, normalise the rest. If two
 * cards come back with the SAME remainder, one sentence is being reused with the word swapped —
 * which is exactly the shape above, and is also exactly what a legitimate pair of examples is not:
 * two different moments of the same scene do not agree word for word around their nouns.
 *
 * Deliberately narrow. It fires only on an EXACT skeleton match, and only when what is left is a
 * real sentence rather than a fragment ({@see MIN_SKELETON_WORDS}) — «I need ␣.» twice is a thin
 * day, not a broken one, and a gate that refused it would refuse a `zero` day where every example
 * is four words long.
 */
final class ExampleSkeleton
{
    /** What a blanked-out term becomes. A private-use codepoint, so no sentence can contain it. */
    private const HOLE = "\u{E001}";

    /**
     * How much sentence has to be left around the holes before two of them are «the same sentence».
     *
     * Three words. Under that the remainder is a frame every short day shares («I need ␣»), and a
     * refusal there would be about the language rather than about the answer.
     */
    private const MIN_SKELETON_WORDS = 3;

    /**
     * The example with every term of the day blanked out, or null when there is nothing to compare.
     *
     * @param  list<string>  $dayTerms  every card text of the day — its own included, longest first
     *                                  is applied here so «lower back» is blanked before «back»
     */
    public function of(string $example, array $dayTerms): ?string
    {
        $skeleton = self::normalize($example);
        if ($skeleton === '') {
            return null;
        }

        $terms = array_values(array_filter(array_map(self::normalize(...), $dayTerms), static fn (string $t): bool => $t !== ''));
        usort($terms, static fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));

        foreach ($terms as $term) {
            $skeleton = (string) preg_replace(
                '/(?<![\p{L}\p{N}])' . preg_quote($term, '/') . '(?![\p{L}\p{N}])/u',
                self::HOLE,
                $skeleton,
            );
        }

        $skeleton = trim((string) preg_replace('/\s+/u', ' ', $skeleton));
        if ($skeleton === '' || ! str_contains($skeleton, self::HOLE)) {
            // Nothing of the day stands in this sentence, so there is no substitution to catch and
            // comparing whole sentences here would merely restate «no two examples are equal».
            return null;
        }

        $words = array_filter(
            explode(' ', str_replace(self::HOLE, ' ', $skeleton)),
            static fn (string $w): bool => $w !== '',
        );

        return count($words) >= self::MIN_SKELETON_WORDS ? $skeleton : null;
    }

    /** Case-folded, punctuation-free, whitespace-collapsed — the day validator's own normalisation. */
    private static function normalize(string $value): string
    {
        $lower = mb_strtolower(trim($value));
        $stripped = preg_replace('/[^\p{L}\p{N}\x{E001}]+/u', ' ', $lower) ?? '';

        return trim((string) preg_replace('/\s+/u', ' ', $stripped));
    }
}
