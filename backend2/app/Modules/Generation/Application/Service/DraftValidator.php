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
     * @param  int|null  $targetCount  how many items to keep (the requested size). Explicit because the
     *                                 model brief now carries an *overshoot* count, not the requested one;
     *                                 defaults to the brief size for callers that don't over-ask.
     * @param  bool  $supplemental     a top-up batch: skip the MIN_ITEMS floor. A top-up returning 2 fresh
     *                                 items is valid, not a truncated-response failure — the primary pass
     *                                 already guaranteed a non-broken set.
     */
    public function validate(
        GeneratedCollectionDraft $draft,
        GenerationBrief $brief,
        ?int $targetCount = null,
        bool $supplemental = false,
    ): GeneratedCollectionDraft {
        $targetCount ??= $brief->size;
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
            $usable = new GeneratedItem(
                text: $text,
                type: $this->type($item->type, $text),
                translation: $translation,
                example: $example,
                cefr: $cefr,
                transcription: $this->nullableText($item->transcription),
                exampleTranslation: $example !== null ? $this->nullableText($item->exampleTranslation) : null,
                imageApiPrompt: $this->nullableText($item->imageApiPrompt), // "" (un-illustratable) → null
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

        // Trim over-generation down to the requested count (bounded by the hard ceiling). The floor
        // only applies to a primary pass; a top-up may legitimately land below MIN_ITEMS.
        // Under-generation is kept as-is — the caller decides whether to top up.
        $target = min(self::MAX_ITEMS, $targetCount);
        if (! $supplemental) {
            $target = max(self::MIN_ITEMS, $target);
        }
        if (count($clean) > $target) {
            $clean = array_slice($clean, 0, $target);
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
