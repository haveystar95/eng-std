<?php

declare(strict_types=1);

namespace App\Modules\Generation\Domain\Service;

use App\Modules\Shared\Domain\Service\LexicalNormalizer;

/**
 * Derives the span/correction a distractor SHOULD have carried, by diffing it against the example it
 * is a broken version of — and, just as importantly, refuses to derive one when the row breaks more
 * than one place.
 *
 * This exists because a retro-audit finds two different things and they deserve opposite treatments.
 * A row whose sentence is a fine one-place break with a mislabelled span is worth keeping: the
 * sentence is the expensive part, the label is mechanical and derivable. A row that changed two
 * separate places is not a distractor at all under our own rules ("each distractor changes exactly ONE
 * thing"), and no relabelling makes it one — the learner would be shown one underline for two errors.
 *
 * The rule for "unambiguous" is therefore literal: align the two token sequences and count the
 * DISJOINT differing regions. Exactly one → the repair is forced and this class returns it. Zero →
 * the distractor is the example. Two or more → nothing is returned, and the caller deletes the row.
 *
 * Tokens are compared through the shared normaliser, so «money.» and «money» are the same token and a
 * difference that is only punctuation never invents a region.
 */
final class DistractorRepair
{
    public function __construct(private readonly LexicalNormalizer $normalizer = new LexicalNormalizer()) {}

    /**
     * The span/correction forced by the diff, or null when the row is not repairable — either because
     * it differs from the example in no place at all, or in more than one.
     *
     * @return array{span: string, correction: string}|null
     */
    public function derive(string $sentence, string $example): ?array
    {
        $from = $this->tokens($sentence);
        $to = $this->tokens($example);

        $regions = $this->regions($from, $to);
        if (count($regions) !== 1) {
            return null;
        }

        [$fromStart, $fromEnd, $toStart, $toEnd] = $regions[0];

        // An insertion or a deletion leaves one side of the region empty, and an empty span cannot be
        // underlined. Widening by one token — to the left where there is one, otherwise to the right —
        // is the deterministic way to give both sides something to show, and it keeps the fix minimal.
        while ($fromStart >= $fromEnd || $toStart >= $toEnd) {
            if ($fromStart > 0 && $toStart > 0) {
                $fromStart--;
                $toStart--;

                continue;
            }
            if ($fromEnd < count($from) && $toEnd < count($to)) {
                $fromEnd++;
                $toEnd++;

                continue;
            }

            return null; // nothing left to widen into: the whole sentence is the difference
        }

        return [
            'span' => implode(' ', array_slice($from, $fromStart, $fromEnd - $fromStart)),
            'correction' => implode(' ', array_slice($to, $toStart, $toEnd - $toStart)),
        ];
    }

    /**
     * Disjoint differing regions between the two token lists, as [fromStart, fromEnd, toStart, toEnd)
     * half-open ranges, via the longest common subsequence.
     *
     * @param  list<string>  $from
     * @param  list<string>  $to
     * @return list<array{0: int, 1: int, 2: int, 3: int}>
     */
    private function regions(array $from, array $to): array
    {
        $lcs = $this->lcsLengths($from, $to);

        $regions = [];
        $i = 0;
        $j = 0;
        $n = count($from);
        $m = count($to);

        while ($i < $n || $j < $m) {
            if ($i < $n && $j < $m && $this->key($from[$i]) === $this->key($to[$j])) {
                $i++;
                $j++;

                continue;
            }

            // Walk the whole run of non-matching tokens on both sides — that run IS one region.
            $fromStart = $i;
            $toStart = $j;
            while ($i < $n || $j < $m) {
                if ($i < $n && $j < $m && $this->key($from[$i]) === $this->key($to[$j])) {
                    break;
                }
                if ($j < $m && ($i >= $n || $lcs[$i][$j + 1] >= $lcs[$i + 1][$j])) {
                    $j++;

                    continue;
                }
                $i++;
            }
            $regions[] = [$fromStart, $i, $toStart, $j];
        }

        return $regions;
    }

    /**
     * @param  list<string>  $from
     * @param  list<string>  $to
     * @return array<int, array<int, int>>  lcs[i][j] = LCS length of from[i..] and to[j..]
     */
    private function lcsLengths(array $from, array $to): array
    {
        $n = count($from);
        $m = count($to);
        $lcs = array_fill(0, $n + 1, array_fill(0, $m + 1, 0));

        for ($i = $n - 1; $i >= 0; $i--) {
            for ($j = $m - 1; $j >= 0; $j--) {
                $lcs[$i][$j] = $this->key($from[$i]) === $this->key($to[$j])
                    ? $lcs[$i + 1][$j + 1] + 1
                    : max($lcs[$i + 1][$j], $lcs[$i][$j + 1]);
            }
        }

        return $lcs;
    }

    /** @return list<string> */
    private function tokens(string $value): array
    {
        $trimmed = trim($value);

        return $trimmed === '' ? [] : (preg_split('/\s+/u', $trimmed) ?: []);
    }

    /** Token identity through the shared normaliser: «money.» and «money» are one token, not two. */
    private function key(string $token): string
    {
        return $this->normalizer->canonicalize($token);
    }
}
