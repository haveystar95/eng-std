<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\Service;

/**
 * A SPACE IS NOT A MISTAKE — where one word ends and the next begins is the recogniser's guess.
 *
 * An on-device recogniser returns a stream of sounds cut into words, and the cut is the part it is
 * least sure of. It writes «withoututilities» for «without utilities» and «down town» for
 * «downtown»; the learner said the same thing either way, and nothing about their memory of the
 * language is different in the two transcripts.
 *
 * The live case this was written for (owner's day 1, 02.09, plan `01M1HZF4…`): the card «I see,
 * without utilities.» was read aloud correctly, the recogniser returned «I see, withoututilities»,
 * and the coverage check found two of four words — 0.5 against a floor of 0.7 — so a correct
 * reading was graded `again`, twice, and the line's stage-A checklist would not close. That is the
 * one outcome the speaking trainer must never produce: it teaches the scheduler that a word is
 * forgotten because a microphone joined two of them.
 *
 * ## What it does, and what it deliberately does not
 *
 * The heard tokens are RE-SEGMENTED against the expected sentence's own vocabulary, in both
 * directions and in that order:
 *
 *   glued   a heard token that is not an expected word, but is exactly two or more expected words
 *           written without the spaces, becomes those words;
 *   split   a run of heard tokens whose concatenation is an expected word becomes that word.
 *
 * Both are keyed on the EXPECTED side: the only re-cut allowed is one that produces words the card
 * actually asked for, so nothing here can invent a word the learner did not say. A dropped word is
 * still a dropped word, and the coverage floor still fails a learner who read half the sentence —
 * «пропуск смысловых слов остаётся честным отказом» (решение владельца).
 *
 * Pure, and mirrored in Dart beside the coverage it belongs to, under the same rule as everything
 * else in this grader: the client's read may never be STRICTER than this one.
 */
final class SpokenWordBoundary
{
    /**
     * How many heard tokens may be joined back into one expected word.
     *
     * Three, because that is what a recogniser does to a compound («out of pocket» for
     * «outofpocket» is already unusual); a wider window would start finding accidental readings
     * inside long sentences.
     */
    private const MAX_JOIN = 3;

    /**
     * The heard words, re-cut so that a boundary the recogniser guessed differently is not counted
     * as a different word.
     *
     * Order is preserved and nothing is dropped: a token that cannot be re-cut into expected words
     * survives exactly as it arrived, so it can still fail to match.
     *
     * @param  list<string>  $heard  the transcript's comparable words
     * @param  list<string>  $expected  the card's own comparable words
     * @return list<string>
     */
    public function align(array $heard, array $expected): array
    {
        if ($heard === [] || $expected === []) {
            return $heard;
        }

        $vocabulary = [];
        foreach ($expected as $word) {
            $vocabulary[$word] = true;
        }

        return $this->join($this->split($heard, $vocabulary), $vocabulary);
    }

    /**
     * Two strings that differ only in where the spaces fall.
     *
     * The equality path's half of the same tolerance — a term short enough to be compared whole
     * ({@see SpokenCoverage::LONG_UTTERANCE_WORDS}) is a phrase like «without utilities», and the
     * recogniser glues those exactly as readily as it glues a sentence's.
     */
    public function equalIgnoringBoundaries(string $a, string $b): bool
    {
        $left = str_replace(' ', '', $a);

        return $left !== '' && $left === str_replace(' ', '', $b);
    }

    /**
     * GLUED APART: «withoututilities» → «without», «utilities».
     *
     * @param  list<string>  $heard
     * @param  array<string, true>  $vocabulary
     * @return list<string>
     */
    private function split(array $heard, array $vocabulary): array
    {
        $out = [];
        foreach ($heard as $word) {
            if (isset($vocabulary[$word])) {
                $out[] = $word;

                continue;
            }
            $pieces = $this->decompose($word, $vocabulary);
            foreach ($pieces ?? [$word] as $piece) {
                $out[] = $piece;
            }
        }

        return $out;
    }

    /**
     * SPLIT BACK TOGETHER: «down», «town» → «downtown».
     *
     * Left to right and greedy, and only from a token the vocabulary does not already know: a run
     * whose first word is an expected word in its own right is a run the sentence asked for, and
     * joining it would take a word away from the count.
     *
     * @param  list<string>  $heard
     * @param  array<string, true>  $vocabulary
     * @return list<string>
     */
    private function join(array $heard, array $vocabulary): array
    {
        $out = [];
        $count = count($heard);
        for ($i = 0; $i < $count; $i++) {
            if (isset($vocabulary[$heard[$i]])) {
                $out[] = $heard[$i];

                continue;
            }

            $joined = $heard[$i];
            $taken = 1;
            for ($span = 1; $span < self::MAX_JOIN && $i + $span < $count; $span++) {
                $joined .= $heard[$i + $span];
                if (isset($vocabulary[$joined])) {
                    $taken = $span + 1;

                    break;
                }
            }

            $out[] = $taken === 1 ? $heard[$i] : $joined;
            $i += $taken - 1;
        }

        return $out;
    }

    /**
     * The expected words this token is made of, in order, or null when it is not made of them.
     *
     * A left-to-right walk that prefers the LONGEST prefix and backtracks, which is the ordinary
     * word-break search: «seeit» is «see» + «it», and «utilities» stays one word because nothing
     * shorter starts it.
     *
     * @param  array<string, true>  $vocabulary
     * @return list<string>|null
     */
    private function decompose(string $word, array $vocabulary, int $depth = 0): ?array
    {
        // A glued token is at most a handful of words; the bound keeps a pathological sentence from
        // turning a grader into a search.
        if ($depth >= self::MAX_JOIN) {
            return null;
        }

        $length = mb_strlen($word);
        for ($take = $length - 1; $take >= 1; $take--) {
            $head = mb_substr($word, 0, $take);
            if (! isset($vocabulary[$head])) {
                continue;
            }

            $tail = mb_substr($word, $take);
            if (isset($vocabulary[$tail])) {
                return [$head, $tail];
            }

            $rest = $this->decompose($tail, $vocabulary, $depth + 1);
            if ($rest !== null) {
                return [$head, ...$rest];
            }
        }

        return null;
    }
}
