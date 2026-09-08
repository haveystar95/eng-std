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
 * And a space is not a word: where the recogniser cut one word from the next is its own guess, so
 * the transcript is re-segmented against the card's own vocabulary before anything is counted
 * ({@see SpokenWordBoundary}). «withoututilities» is «without utilities» said correctly, not two
 * words missing.
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

    /**
     * From how many words an utterance stops being «a word» and starts being a phrase — the point
     * at which a spoken answer is compared by coverage instead of by equality.
     *
     * A term is not always a word. «Where do you see yourself in five years?» is one row in the
     * catalogue and a whole sentence in the microphone, and every reason equality is the wrong bar
     * for an EXAMPLE ({@see the class docblock}) applies to it word for word: the recogniser eats
     * the same function words and guesses the same homophones. Held to equality, a correct reading
     * of a phrase term graded `again` and handed the scheduler a lapse — which is the one outcome
     * this trainer must never produce (BUGFIX-2 Ч.3б, owner's device: «фразы распознаются
     * нестабильно»).
     *
     * Mirrored on the client as `SpokenAnswer.longTermWords`, which picks the recording WINDOW off
     * the same number — «длинность» has one definition, so a term recorded like a sentence is
     * exactly the term graded like one. Before this the client already graded such a term by
     * coverage and the server still demanded equality (QA-22 landed on one side only), so the phone
     * showed «Верно» and the scheduler wrote a lapse behind it.
     */
    public const LONG_UTTERANCE_WORDS = 3;

    /** Is this expected answer long enough to be compared by coverage? See {@see LONG_UTTERANCE_WORDS}. */
    public static function isLongUtterance(string $expected): bool
    {
        $trimmed = trim($expected);

        return $trimmed !== '' && count(preg_split('/\s+/', $trimmed) ?: []) >= self::LONG_UTTERANCE_WORDS;
    }

    public function __construct(
        private LexicalNormalizer $normalizer = new LexicalNormalizer(),
        private SpokenSuffixTolerance $suffixTolerance = new SpokenSuffixTolerance(),
        /**
         * WHERE THE WORDS WERE CUT — the recogniser's own guess, and not evidence about the
         * learner. {@see SpokenWordBoundary}, added after a correct reading of «I see, without
         * utilities.» came back as «I see, withoututilities» and scored 0.5.
         */
        private SpokenWordBoundary $boundary = new SpokenWordBoundary(),
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

        // RE-CUT AGAINST THE CARD'S OWN WORDS FIRST. A boundary the recogniser put in the wrong
        // place is not a missing word, and the count below cannot tell the two apart on its own.
        $available = array_count_values($this->boundary->align($this->words($response), $wanted));

        $found = 0;
        foreach ($wanted as $word) {
            if ($this->consume($available, $word)) {
                $found++;
            }
        }

        return $found / count($wanted);
    }

    /**
     * КАКИХ СЛОВ ЦЕЛИ НЕ ХВАТИЛО — в том виде, в каком они написаны на карточке, в порядке чтения
     * (наряд SPEECH-2, Ч.3.5). Зеркало клиентского `SessionGrader.uncoveredWords`.
     *
     * Считается ТЕМ ЖЕ ходом, что и {@see ratio()} — те же границы слов, тот же хвостовой сибилянт,
     * тот же мультимножественный расход, — иначе вердикт говорил бы «не хватило X», когда покрытие
     * X засчитало. Группировка по НАПИСАННОМУ слову, а не по канонизированному токену: «don't» —
     * это одно слово на экране и два токена внутри, и человеку показывают слово.
     *
     * @return list<string>
     */
    public function missing(string $response, string $expected): array
    {
        $raw = trim($expected);
        if ($raw === '') {
            return [];
        }
        $rawWords = preg_split('/\s+/u', $raw) ?: [];
        $available = array_count_values($this->boundary->align($this->words($response), $this->words($expected)));

        $missing = [];
        foreach ($rawWords as $word) {
            $tokens = $this->words($word);
            // Слово, которое канонизируется в пустоту (одна пунктуация), не пропущено и не найдено.
            if ($tokens === []) {
                continue;
            }
            foreach ($tokens as $token) {
                if (! $this->consume($available, $token)) {
                    $missing[] = $word;
                    break;
                }
            }
        }

        return $missing;
    }

    /**
     * Marks one occurrence of $word as used in $available and returns true — exact first, then a
     * suffix-tolerant match (QA-20: a recogniser drops a trailing sibilant far more than it
     * invents or swaps a whole word). $available is small (one sentence), so a linear scan for the
     * tolerant match costs nothing that matters here.
     *
     * `(string) $candidate`, and not decoration: `array_count_values` hands back an INT key for a
     * word that is all digits, so a transcript containing «5» («in 5 years», which is exactly what a
     * recogniser writes for «five») made the tolerant scan throw a TypeError and the whole review
     * batch 500. Found the moment a phrase TERM started being compared this way (BUGFIX-2 Ч.3б).
     *
     * @param  array<array-key, int>  $available
     */
    private function consume(array &$available, string $word): bool
    {
        if (($available[$word] ?? 0) > 0) {
            $available[$word]--;

            return true;
        }
        foreach ($available as $candidate => $count) {
            if ($count > 0 && $this->suffixTolerance->equal($word, (string) $candidate)) {
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
