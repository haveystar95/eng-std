<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Service;

use App\Modules\Generation\Application\Dto\AssembledDraft;
use App\Modules\Generation\Application\Dto\AttemptUsage;
use App\Modules\Generation\Application\Dto\GeneratedCollectionDraft;
use App\Modules\Generation\Application\Dto\GeneratedItem;
use App\Modules\Generation\Application\Dto\GenerationBrief;
use App\Modules\Generation\Application\Port\CollectionGeneratorPort;
use App\Modules\Shared\Domain\Service\ModelCost;

/**
 * The generate → validate → top-up pipeline, in one place so every caller measures the same thing.
 * Models routinely under-deliver ("asked 15, got 13"), so this overshoots the ask by ~30%, trims
 * back to the requested size, and — if still short — does ONE more call with an avoid list, never a
 * loop. Tokens/cost are summed across both calls. Owned here (not in the command handler) so the
 * eval tool exercises the exact production behaviour rather than a drifting copy.
 */
final readonly class GenerationPipeline
{
    private ModelCost $cost;

    public function __construct(
        private CollectionGeneratorPort $generator,
        private DraftValidator $validator,
    ) {
        $this->cost = new ModelCost();
    }

    /**
     * @param  GenerationBrief  $brief  its `size` is the *requested* count (not the overshoot ask).
     * @param  callable(AttemptUsage): void  $onAttempt  invoked after each model call with the running
     *         spend total, BEFORE validation — so a caller can persist spend that a later validation
     *         failure must not erase. A primary draft that fails validation throws (terminal, no retry);
     *         a top-up is supplemental and never throws for being small.
     */
    public function assemble(GenerationBrief $brief, callable $onAttempt): AssembledDraft
    {
        $requested = $brief->size;

        // Overshoot, capped at the hard ceiling — for a request already at the max there's no headroom.
        $overshoot = min(DraftValidator::MAX_ITEMS, (int) ceil($requested * 1.3));

        $raw = $this->generator->generate($this->resize($brief, $overshoot));

        $model = $raw->model;
        $tokensIn = $raw->tokensIn;
        $tokensOut = $raw->tokensOut;
        $costUsd = $this->estimateCost($raw->model, $raw->tokensIn, $raw->tokensOut);
        $onAttempt(new AttemptUsage($model, $tokensIn, $tokensOut, $costUsd, $raw->rawResponse));

        // Primary pass: filter/dedup and trim to the requested count. The MIN_ITEMS floor still applies
        // here — a genuinely broken/truncated first response throws (terminal failure, no retry).
        $draft = $this->validator->validate($raw, $this->resize($brief, $overshoot), targetCount: $requested);

        if (count($draft->items) < $requested) {
            $shortfall = $requested - count($draft->items);
            $topUpSize = min(DraftValidator::MAX_ITEMS, (int) ceil($shortfall * 1.3));
            $avoid = array_map(static fn (GeneratedItem $i): string => $i->text, $draft->items);
            $topUpBrief = $this->resize($brief, $topUpSize, $avoid);

            $topUpRaw = $this->generator->generate($topUpBrief);

            // Sum, never overwrite: tokens/cost of the request are the total of both model calls.
            $tokensIn = $this->sumTokens($tokensIn, $topUpRaw->tokensIn);
            $tokensOut = $this->sumTokens($tokensOut, $topUpRaw->tokensOut);
            $costUsd = $this->sumCost($costUsd, $this->estimateCost($topUpRaw->model, $topUpRaw->tokensIn, $topUpRaw->tokensOut));
            $onAttempt(new AttemptUsage($model, $tokensIn, $tokensOut, $costUsd, $topUpRaw->rawResponse));

            // Supplemental: no floor — a top-up of a couple of items is valid. Cross-dedup + trim to
            // the requested size happens in the merge.
            $topUp = $this->validator->validate($topUpRaw, $topUpBrief, targetCount: $topUpSize, supplemental: true);
            $draft = $this->merge($draft, $topUp, $requested);
        }

        return new AssembledDraft(
            draft: $draft,
            primaryRaw: $raw,
            model: $model,
            tokensIn: $tokensIn,
            tokensOut: $tokensOut,
            costUsd: $costUsd,
            delivered: count($draft->items),
        );
    }

    /** @param list<string> $excludeTexts */
    private function resize(GenerationBrief $brief, int $size, array $excludeTexts = []): GenerationBrief
    {
        return new GenerationBrief(
            prompt: $brief->prompt,
            sourceLang: $brief->sourceLang,
            targetLang: $brief->targetLang,
            levels: $brief->levels,
            size: $size,
            excludeTexts: $excludeTexts,
        );
    }

    /**
     * Primary items first, then the fresh top-up items, deduped by lowercased text and trimmed to
     * the requested count. Keeps the primary pass's title/description.
     */
    private function merge(GeneratedCollectionDraft $primary, GeneratedCollectionDraft $topUp, int $requested): GeneratedCollectionDraft
    {
        $seen = [];
        $items = [];
        foreach ([...$primary->items, ...$topUp->items] as $item) {
            $key = mb_strtolower($item->text);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $items[] = $item;
        }

        return new GeneratedCollectionDraft(
            title: $primary->title,
            description: $primary->description,
            items: array_slice($items, 0, $requested),
            model: $primary->model,
            tokensIn: $primary->tokensIn,
            tokensOut: $primary->tokensOut,
            rawResponse: $primary->rawResponse,
            imageApiPrompt: $primary->imageApiPrompt, // keep the primary pass's cover query
        );
    }

    private function sumTokens(?int $a, ?int $b): ?int
    {
        if ($a === null && $b === null) {
            return null;
        }

        return (int) $a + (int) $b;
    }

    private function sumCost(?string $a, ?string $b): ?string
    {
        if ($a === null && $b === null) {
            return null;
        }

        return number_format((float) $a + (float) $b, 6, '.', '');
    }

    public function estimateCost(string $model, ?int $tokensIn, ?int $tokensOut): ?string
    {
        return $this->cost->estimate($model, $tokensIn, $tokensOut);
    }
}
