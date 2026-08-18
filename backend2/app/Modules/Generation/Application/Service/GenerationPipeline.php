<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Service;

use App\Modules\Generation\Application\Dto\AssembledDraft;
use App\Modules\Generation\Application\Dto\AttemptUsage;
use App\Modules\Generation\Application\Dto\GeneratedCollectionDraft;
use App\Modules\Generation\Application\Dto\GeneratedItem;
use App\Modules\Generation\Application\Dto\GenerationBrief;
use App\Modules\Generation\Application\Dto\RepairedTranslation;
use App\Modules\Generation\Application\Port\CollectionGeneratorPort;
use App\Modules\Shared\Domain\Service\ModelCost;

/**
 * The generate → validate → screen → top-up pipeline, in one place so every caller measures the
 * same thing. Models routinely under-deliver ("asked 15, got 13"), so this overshoots the ask by
 * ~30%, and — if still short — does ONE more call with an avoid list, never a loop. Size is
 * approximate (owner decision, 2026-08-18): every valid item ships, capped only at the hard
 * ceiling `DraftValidator::MAX_ITEMS`, never trimmed down toward the requested count. Tokens/cost
 * are summed across every call, repairs included. Owned here (not in the command handler) so the
 * eval tool exercises the exact production behaviour rather than a drifting copy.
 *
 * The language barrier sits between validation and the caller: nothing leaves this class in the
 * wrong language, and what it refused travels alongside the draft rather than disappearing into a
 * smaller item count.
 */
final readonly class GenerationPipeline
{
    private ModelCost $cost;

    public function __construct(
        private CollectionGeneratorPort $generator,
        private DraftValidator $validator,
        private LanguageBarrier $barrier,
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

        // Primary pass: filter/dedup, cap at MAX_ITEMS. The MIN_ITEMS floor still applies here — a
        // genuinely broken/truncated first response throws (terminal failure, no retry).
        $draft = $this->validator->validate($raw, $this->resize($brief, $overshoot));

        // Screen BEFORE the shortfall check, so an item dropped for language leaves a hole the
        // top-up fills like any other under-delivery. A collection that quietly came back two items
        // short because two translations were Ukrainian is the exact silence this whole change exists
        // to remove — the hole is filled, and what made it is returned.
        $screened = $this->barrier->screen($draft->items, $brief);
        $rejections = $screened->rejections;
        $draft = $this->withItems($draft, $screened->items);
        if ($screened->repairs !== []) {
            [$tokensIn, $tokensOut, $costUsd] = $this->addRepairs($tokensIn, $tokensOut, $costUsd, $screened->repairs);
            $onAttempt(new AttemptUsage($model, $tokensIn, $tokensOut, $costUsd, $raw->rawResponse));
        }

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

            // Supplemental: no floor — a top-up of a couple of items is valid. Cross-dedup + the
            // MAX_ITEMS cap happens in the merge.
            $topUp = $this->validator->validate($topUpRaw, $topUpBrief, supplemental: true);

            // The top-up is model output like any other and gets the same barrier. There is no
            // third pass: a top-up that also comes back tainted under-delivers, honestly logged.
            $screenedTopUp = $this->barrier->screen($topUp->items, $brief);
            foreach ($screenedTopUp->rejections as $rejection) {
                $rejections[] = $rejection;
            }
            if ($screenedTopUp->repairs !== []) {
                [$tokensIn, $tokensOut, $costUsd] = $this->addRepairs($tokensIn, $tokensOut, $costUsd, $screenedTopUp->repairs);
                $onAttempt(new AttemptUsage($model, $tokensIn, $tokensOut, $costUsd, $topUpRaw->rawResponse));
            }

            $draft = $this->merge($draft, $this->withItems($topUp, $screenedTopUp->items));
        }

        return new AssembledDraft(
            draft: $draft,
            primaryRaw: $raw,
            model: $model,
            tokensIn: $tokensIn,
            tokensOut: $tokensOut,
            costUsd: $costUsd,
            delivered: count($draft->items),
            rejections: $rejections,
        );
    }

    /**
     * Fold every repair call's usage into the running total. Failed repairs are in here too: a call
     * that answered in Ukrainian again cost exactly what a good one costs, and a spend figure that
     * omitted it would make bad runs look cheap.
     *
     * The request carries ONE model name, and it stays the generator's — the repairer runs on a
     * different (cheaper) model, so its tokens are summed but its price is estimated at its own rate.
     *
     * @param  list<RepairedTranslation>  $repairs
     * @return array{0: int|null, 1: int|null, 2: string|null}
     */
    private function addRepairs(?int $tokensIn, ?int $tokensOut, ?string $costUsd, array $repairs): array
    {
        foreach ($repairs as $repair) {
            $tokensIn = $this->sumTokens($tokensIn, $repair->tokensIn);
            $tokensOut = $this->sumTokens($tokensOut, $repair->tokensOut);
            $costUsd = $this->sumCost($costUsd, $this->estimateCost($repair->model, $repair->tokensIn, $repair->tokensOut));
        }

        return [$tokensIn, $tokensOut, $costUsd];
    }

    /** @param  list<GeneratedItem>  $items */
    private function withItems(GeneratedCollectionDraft $draft, array $items): GeneratedCollectionDraft
    {
        return new GeneratedCollectionDraft(
            title: $draft->title,
            description: $draft->description,
            items: $items,
            model: $draft->model,
            tokensIn: $draft->tokensIn,
            tokensOut: $draft->tokensOut,
            rawResponse: $draft->rawResponse,
            imageApiPrompt: $draft->imageApiPrompt,
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
     * Primary items first, then the fresh top-up items, deduped by lowercased text and capped at
     * MAX_ITEMS. Keeps the primary pass's title/description.
     */
    private function merge(GeneratedCollectionDraft $primary, GeneratedCollectionDraft $topUp): GeneratedCollectionDraft
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
            items: array_slice($items, 0, DraftValidator::MAX_ITEMS),
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
