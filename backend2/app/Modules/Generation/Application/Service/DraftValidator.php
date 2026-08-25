<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Service;

use App\Modules\Generation\Application\Dto\GeneratedCollectionDraft;
use App\Modules\Generation\Application\Dto\GeneratedItem;
use App\Modules\Generation\Application\Dto\GenerationBrief;
use App\Modules\Generation\Domain\Exception\InvalidGeneratedDraft;

/**
 * Never trust the model. Drop empty/out-of-level/duplicate items, cap the size, infer the
 * word/phrase type when the model omits it, and reject the whole draft if too little
 * survives (a truncated response means max_tokens was hit — better to fail than ship junk).
 */
final class DraftValidator
{
    public const MIN_ITEMS = 8;
    public const MAX_ITEMS = 25;
    private const CEFR_ORDER = ['A1' => 1, 'A2' => 2, 'B1' => 3, 'B2' => 4, 'C1' => 5, 'C2' => 6];

    /**
     * @param  bool  $supplemental  a top-up batch: skip the MIN_ITEMS floor. A top-up returning 2 fresh
     *                              items is valid, not a truncated-response failure — the primary pass
     *                              already guaranteed a non-broken set.
     */
    public function validate(
        GeneratedCollectionDraft $draft,
        GenerationBrief $brief,
        bool $supplemental = false,
    ): GeneratedCollectionDraft {
        [$min, $max] = $this->levelRange($brief->levels);

        $seen = [];
        /** @var list<GeneratedItem> $all every valid, de-duplicated item */
        $all = [];
        /** @var list<GeneratedItem> $inLevel the subset within the requested CEFR band */
        $inLevel = [];
        foreach ($draft->items as $item) {
            $text = trim($item->text);
            $translation = trim($item->translation);
            if ($text === '' || $translation === '') {
                continue;
            }

            $key = mb_strtolower($text);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $cefr = $this->cefr($item->cefr);
            // An example that merely repeats the term teaches nothing and is dropped; its
            // translation goes with it, because a translation of a sentence that is not there is
            // not a fact about anything.
            $example = $this->teachingExample($item->example, $text);
            $type = $this->type($item->type, $text);
            // …and only now the text is settled: a sentence-like item gets its capital back. The
            // echo check above is case-insensitive, so it does not care which of the two runs first.
            $text = $this->sentenceCase($text, $type);
            $usable = new GeneratedItem(
                text: $text,
                type: $type,
                translation: $translation,
                example: $example,
                cefr: $cefr,
                transcription: $this->nullableText($item->transcription),
                exampleTranslation: $example !== null ? $this->nullableText($item->exampleTranslation) : null,
                imageApiPrompt: $this->nullableText($item->imageApiPrompt), // "" (un-illustratable) → null
                // Carried through untouched. This validator judges the CORE — the key, the example,
                // the level — and has no opinion about the per-pair products; each of those has its
                // own gate further down ({@see \App\Modules\Generation\Domain\Service\EnrichmentValidator}).
                // Dropping them here is how they silently never reached the database: the item is
                // REBUILT, so a field this constructor does not name is a field that ceases to exist.
                synonyms: $item->synonyms,
                otherTranslations: $item->otherTranslations,
                transliteration: $this->nullableText($item->transliteration),
                description: $this->nullableText($item->description),
            );
            $all[] = $usable;
            if ($this->withinLevel($cefr, $min, $max)) {
                $inLevel[] = $usable;
            }
        }

        // The CEFR band is a PREFERENCE, not a hard gate. Keep in-level items when there are enough
        // of them; but if the topic simply doesn't yield MIN_ITEMS at the requested level — a basic
        // topic like "ordering at a restaurant" under a single high level (B2) — fall back to all
        // valid items rather than reject the whole draft (device-batch F14). A genuinely short or
        // truncated response (too few valid items even unfiltered) still fails below.
        $clean = count($inLevel) >= self::MIN_ITEMS ? $inLevel : $all;

        if (! $supplemental && count($clean) < self::MIN_ITEMS) {
            throw InvalidGeneratedDraft::because('only ' . count($clean) . ' usable items after validation');
        }

        // Size is approximate (owner decision, 2026-08-18): every valid item ships. The only cap
        // left is the hard ceiling MAX_ITEMS — trimming down toward the requested count used to
        // throw away valid items (array_slice is positional, so it discarded whichever items
        // happened to land late in the model's response, not the worst ones) for no reason beyond
        // matching a count nobody promised exactly.
        if (count($clean) > self::MAX_ITEMS) {
            $clean = array_slice($clean, 0, self::MAX_ITEMS);
        }

        return new GeneratedCollectionDraft(
            title: trim($draft->title) !== '' ? trim($draft->title) : 'New collection',
            description: $this->nullableText($draft->description),
            items: $clean,
            model: $draft->model,
            tokensIn: $draft->tokensIn,
            tokensOut: $draft->tokensOut,
            rawResponse: $draft->rawResponse,
            imageApiPrompt: $this->nullableText($draft->imageApiPrompt),
        );
    }

    /**
     * The example, unless it is the term back again — in which case there is no example.
     *
     * An example exists to show the term IN USE. Three of ten items in the acceptance collection came
     * back with `example` character-for-character equal to `text` («Where can I find dog food?»
     * taught by «Where can I find dog food?»), so the intro card showed the same sentence twice and
     * offered no context at all (QA-7). It is not a property of sentence-like terms — two other
     * phrases and an idiom in the very same response got proper contextual examples — it is the
     * model being inconsistent, which is exactly what validation is for.
     *
     * The test is EQUALITY after trimming and case-folding, and deliberately no looser. An example
     * that CONTAINS the term is the normal, correct case — that is what using a word in a sentence
     * means — and a similarity threshold would start throwing those away.
     *
     * Rejected here rather than repaired: the term keeps everything else and goes in without an
     * example, and {@see RepairEchoExamplesHandler} generates a real one afterwards, on its own job,
     * off the path the learner is waiting on.
     */
    private function teachingExample(?string $example, string $text): ?string
    {
        $trimmed = $this->nullableText($example);
        if ($trimmed === null) {
            return null;
        }

        return self::isEcho($trimmed, $text) ? null : $trimmed;
    }

    /**
     * A sentence written as a sentence: first letter capital.
     *
     * «do you have any discounts?» arrived among nine correctly capitalised neighbours in the same
     * response — «Where can I find dog food?», «Is this suitable for small breeds?» — so on the card
     * it read as a typo the app had made (QA-2). The model is inconsistent about exactly this and
     * about nothing else, which is what makes it a job for validation rather than for the prompt.
     *
     * Applied ONLY to an item that ends in sentence-final punctuation, which is the narrowest test
     * that catches the defect. The rule «capitalise every phrase» is what the finding literally asks
     * for and it would be wrong: `phrase` is also the type of «aisle seat», «baggage claim»,
     * «check-in desk» — noun phrases that are correctly lowercase and would be disfigured by a
     * capital. Words and idioms are never touched at all («grain-free» is legal as it stands).
     *
     * The dedup key is taken before this, from the lowercased text, so nothing about matching moves.
     */
    private function sentenceCase(string $text, string $type): string
    {
        if ($type !== 'phrase' || ! preg_match('/[.?!…]$/u', $text)) {
            return $text;
        }

        return mb_strtoupper(mb_substr($text, 0, 1)) . mb_substr($text, 1);
    }

    /**
     * Does this example merely repeat the term? Shared with the repair pass, so «what counts as an
     * echo» is asked once — the pass that finds them and the pass that rejects them must agree, or
     * the repair would either miss rows or churn on rows the validator considers fine.
     */
    public static function isEcho(string $example, string $text): bool
    {
        return mb_strtolower(trim($example)) === mb_strtolower(trim($text));
    }

    /** Whether an item sits inside the requested CEFR band. No band, or no/unknown item level, is neutral (in). */
    private function withinLevel(?string $cefr, ?int $min, ?int $max): bool
    {
        if ($min === null || $max === null || $cefr === null || ! isset(self::CEFR_ORDER[$cefr])) {
            return true;
        }
        $rank = self::CEFR_ORDER[$cefr];

        return $rank >= $min && $rank <= $max;
    }

    private function type(string $type, string $text): string
    {
        // The known taxonomy (mirrors Vocabulary's TermType; inlined to avoid a cross-module Domain
        // import). Anything else falls back to the whitespace heuristic — never trust a stray label.
        if (in_array($type, ['word', 'phrase', 'idiom', 'phrasal_verb'], true)) {
            return $type;
        }

        return str_contains($text, ' ') ? 'phrase' : 'word';
    }

    private function cefr(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $upper = strtoupper(trim($value));

        return $upper !== '' ? $upper : null;
    }

    private function nullableText(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }

    /**
     * @param  list<string>  $levels
     * @return array{0: int|null, 1: int|null}
     */
    private function levelRange(array $levels): array
    {
        $ranks = [];
        foreach ($levels as $level) {
            $upper = strtoupper($level);
            if (isset(self::CEFR_ORDER[$upper])) {
                $ranks[] = self::CEFR_ORDER[$upper];
            }
        }

        return $ranks === [] ? [null, null] : [min($ranks), max($ranks)];
    }
}
