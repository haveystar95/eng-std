<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\Service;

use App\Modules\Shared\Domain\Service\LexicalNormalizer;

/**
 * Did the learner say ENOUGH of this sentence? The lenient comparison behind
 * {@see \App\Modules\Learning\Domain\ValueObject\MatchPolicy::Coverage}.
 *
 * Why a sentence read aloud cannot be graded by equality. An on-device recogniser transcribes an
 * ordinary reading of «Could you take a photo of us?» as «could you take a photo of us», «could you
 * take photo of us», «could you take a photo of as» — it drops unstressed function words, it
 * guesses between homophones, and it punctuates however it likes. Every one of those readings is a
 * learner who did exactly what the card asked. Holding them to an exact match would score a correct
 * reading `again` and hand a LAPSE to the scheduler, which is the one outcome this whole trainer
 * must never produce: it would teach the app that a word is forgotten because a room was noisy.
 *
 * So the bar is coverage of the sentence's own words, order-free. Order-free because a recogniser's
 * mistakes are substitutions and drops, not transpositions — a learner who reads the words in a
 * different order has not read the sentence, but that is not a thing recognisers invent, whereas
 * dropped words are what they do constantly. Counting is by MULTISET, so a sentence saying «very»
 * twice needs it twice.
 *
 * The threshold is a share rather than a count so it scales with the sentence: four words out of
 * five and eight out of ten are the same reading, and the same verdict. 70% lands where the corpus
 * puts the recogniser's own losses — it forgives the article and the preposition it usually eats,
 * and it still fails a learner who read half the sentence and stopped.
 *
 * Pure, no clock, no storage — and mirrored in Dart, because the device shows an instant verdict
 * offline. The mirror is pinned by the same rule the rest of the grader is: the client's read may
 * never be STRICTER than this one.
 */
final readonly class SpokenCoverage
{
    /**
     * The share of the expected sentence's words that must appear in what was heard.
     *
     * Mirrored by the client. Moving it moves both, or the phone shows a verdict the server then
     * contradicts.
     */
    public const MIN_COVERAGE = 0.7;

    public function __construct(
        private LexicalNormalizer $normalizer = new LexicalNormalizer(),
        private SpokenSuffixTolerance $suffixTolerance = new SpokenSuffixTolerance(),
    ) {}

    /** Was enough of [$expected] present in [$response]? */
    public function covers(string $response, string $expected): bool
    {
        return $this->ratio($response, $expected) >= self::MIN_COVERAGE;
    }

    /**
     * The share of $expected's words present in $response, 0…1. An empty expectation covers
     * nothing — 0.0 rather than a division by zero or a vacuous 1.0, because "the card had no
     * sentence" must not read as "the learner said it".
     */
    public function ratio(string $response, string $expected): float
    {
        $wanted = $this->words($expected);
        if ($wanted === []) {
            return 0.0;
        }

        $available = array_count_values($this->words($response));

        $found = 0;
        foreach ($wanted as $word) {
            if ($this->consume($available, $word)) {
                $found++;
            }
        }

        return $found / count($wanted);
    }

    /**
     * Marks one occurrence of $word as used in $available and returns true — exact first, then a
     * suffix-tolerant match (QA-20: a recogniser drops a trailing sibilant far more than it
     * invents or swaps a whole word). $available is small (one sentence), so a linear scan for the
     * tolerant match costs nothing that matters here.
     *
     * @param  array<string, int>  $available
     */
    private function consume(array &$available, string $word): bool
    {
        if (($available[$word] ?? 0) > 0) {
            $available[$word]--;

            return true;
        }
        foreach ($available as $candidate => $count) {
            if ($count > 0 && $this->suffixTolerance->equal($word, $candidate)) {
                $available[$candidate]--;

                return true;
            }
        }

        return false;
    }

    /**
     * The comparable words of a string.
     *
     * `canonicalize` and not `normalize`: the latter drops a LEADING article, which is right for a
     * whole answer and wrong for a bag of words — here every article is just another word that the
     * recogniser may or may not have caught, and the threshold is what forgives it.
     *
     * @return list<string>
     */
    private function words(string $value): array
    {
        $canonical = $this->normalizer->canonicalize($value);
        if ($canonical === '') {
            return [];
        }

        return explode(' ', $canonical);
    }
}
