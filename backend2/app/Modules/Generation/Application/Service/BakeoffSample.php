<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Service;

use App\Modules\Generation\Domain\Service\KeyIsomorphism;
use App\Modules\Vocabulary\Application\Dto\TranslationKeyRow;
use App\Modules\Vocabulary\Application\Query\TranslationKeyReader;

/**
 * The ~30 live terms track Б is run on, chosen by RULE rather than by hand.
 *
 * The composition is the наряд's: plain single words, multi-word phrases, terms carrying an
 * addressee (`us`/`me`/`you`/`we` — the class the last two content sweeps were about), and a few
 * already-known-bad ones. The last bucket is DERIVED — the terms today's isomorphism rule already
 * flags — rather than a hardcoded list of ids: a hand-picked "known problem" is a term someone
 * remembered, and the ones nobody remembered are exactly the interesting ones.
 *
 * Deterministic by construction: buckets are filled in a fixed order from a list sorted by term id
 * (a ULID, so by creation order). The same store gives the same sample tomorrow, which is what makes
 * two runs comparable — and every provider in a run gets the SAME thirty terms.
 *
 * It only READS. Nothing here writes, and the rows it returns are copied into the sandbox.
 */
final readonly class BakeoffSample
{
    /** Buckets, in fill order, with how many of the sample each may claim. */
    private const QUOTAS = ['addressee' => 10, 'phrase' => 8, 'word' => 8, 'flagged' => 4];

    /** The addressee words that make a term interesting — the source side of the rule's triggers. */
    private const ADDRESSEE = ['us', 'me', 'you', 'your', 'we', 'our', 'my'];

    public function __construct(
        private TranslationKeyReader $keys,
        private KeyIsomorphism $isomorphism,
    ) {}

    /**
     * The sample, read straight from the store.
     *
     * The read lives HERE rather than in the command that prints it: reaching into Vocabulary is a
     * cross-module call, and those go through Application — a console command is allowed neither the
     * dependency nor the decision about what a fair sample is.
     *
     * @param  string  $termLang  the language of the terms themselves ('en')
     * @param  string  $lang  the learner's language, whose translations are judged ('ru')
     * @return list<array{id: string, text: string, translation: string, bucket: string}>
     */
    public function pick(string $termLang, string $lang, int $size = 30): array
    {
        $rows = $this->keys->primaryKeys($termLang, $lang);

        usort($rows, static fn (TranslationKeyRow $a, TranslationKeyRow $b): int => [$a->termId, $a->translationId] <=> [$b->termId, $b->translationId]);

        $buckets = ['addressee' => [], 'phrase' => [], 'word' => [], 'flagged' => []];

        // One row per TERM. The store holds nine terms with two `is_primary` translations each
        // («stay calm» → «Оставайтесь спокойны» AND «оставаться спокойным»), which is a content
        // defect in its own right — the card's question is then whichever of the two a query
        // happens to return. Here it would silently spend two calls on one term and shrink the
        // sample; the lowest translation id wins, so the pick stays deterministic.
        $seen = [];

        foreach ($rows as $row) {
            if (isset($seen[$row->termId])) {
                continue;
            }
            $seen[$row->termId] = true;

            $bucket = $this->bucketOf($row, $lang);
            $buckets[$bucket][] = [
                'id' => $row->termId,
                'text' => $row->termText,
                'translation' => $row->translation,
                'bucket' => $bucket,
            ];
        }

        // Flagged first: it is the smallest bucket and the one a shortfall would silently empty.
        $picked = [];
        foreach (['flagged', 'addressee', 'phrase', 'word'] as $name) {
            foreach (array_slice($buckets[$name], 0, self::QUOTAS[$name]) as $row) {
                $picked[] = $row;
            }
        }

        // A bucket that came up short leaves room; anything not already taken fills it, so the
        // sample is always the size asked for when the store can supply it at all.
        if (count($picked) < $size) {
            $taken = [];
            foreach ($picked as $row) {
                $taken[$row['id']] = true;
            }
            foreach (['addressee', 'phrase', 'word', 'flagged'] as $name) {
                foreach ($buckets[$name] as $row) {
                    if (count($picked) >= $size) {
                        break 2;
                    }
                    if (! isset($taken[$row['id']])) {
                        $picked[] = $row;
                        $taken[$row['id']] = true;
                    }
                }
            }
        }

        return array_slice($picked, 0, $size);
    }

    /**
     * Which bucket a term belongs to. Checked in priority order, so a phrase carrying `us` counts as
     * an addressee term — that is the property being sampled for, and counting it as a phrase would
     * fill the interesting bucket with whatever was left.
     */
    private function bucketOf(TranslationKeyRow $row, string $lang): string
    {
        if ($this->isomorphism->knows($lang) && $this->isomorphism->gaps($row->termText, $row->translation, $lang) !== []) {
            return 'flagged';
        }

        foreach (self::ADDRESSEE as $word) {
            if (preg_match('/(?<![\p{L}\p{N}])' . preg_quote($word, '/') . '(?![\p{L}\p{N}])/iu', $row->termText) === 1) {
                return 'addressee';
            }
        }

        return str_contains(trim($row->termText), ' ') ? 'phrase' : 'word';
    }
}
