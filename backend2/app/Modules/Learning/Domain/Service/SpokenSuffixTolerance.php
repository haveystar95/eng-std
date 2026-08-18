<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\Service;

/**
 * Forgives the CHANNEL, not the memory (QA-20): an on-device recogniser drops a trailing
 * sibilant far more than it invents or swaps a whole word, so "salary expectation" for a target
 * of "salary expectations" is a microphone/recogniser fact, not a sign the learner didn't know
 * the word — the same frame {@see ExerciseMode::Speaking} already applies to silence and retries.
 *
 * Deliberately narrow to three tails and nothing else: `expect` vs `expectations` differs by
 * `ations`, not by one of these, and stays a miss. This is not typo leniency — speaking has none,
 * because a recogniser returns whole words, never a slipped key ({@see ExerciseMode::forgivesTypos()})
 * — it is leniency for exactly one class of channel loss: the ending a recogniser is most likely
 * to have genuinely not heard.
 *
 * `'s` is spelled here as a SEPARATE trailing token (` s`), not glued to the word, because both
 * sides of every comparison this runs against have already been through
 * {@see \App\Modules\Shared\Domain\Service\LexicalNormalizer}, which turns the apostrophe itself
 * into a space — an elided possessive marker survives normalisation as one extra "s" word, not as
 * a suffix on the previous one.
 *
 * Used by both the word-form path ({@see AnswerGrader}, whole compared string) and the
 * example-form path ({@see SpokenCoverage}, per word of the sentence) — one rule, not two copies
 * that could drift.
 */
final class SpokenSuffixTolerance
{
    private const TAILS = ['s', 'es', ' s'];

    /** Same, once exactly one of the tolerated tails is allowed for on either side. */
    public function equal(string $a, string $b): bool
    {
        if ($a === $b) {
            return true;
        }
        foreach (self::TAILS as $tail) {
            if ($a . $tail === $b || $b . $tail === $a) {
                return true;
            }
        }

        return false;
    }
}
