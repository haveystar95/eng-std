<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Service;

use App\Modules\Generation\Application\Dto\GenerationStackConfig;
use App\Modules\Generation\Application\Dto\ShowcaseCostEstimate;
use App\Modules\Generation\Application\Port\ObservedTokenAverages;
use App\Modules\Generation\Application\Port\PromptSource;
use App\Modules\Generation\Domain\ValueObject\PromptShape;
use App\Modules\Shared\Domain\Service\ModelCost;

/**
 * What regenerating N terms would cost, before anyone spends it.
 *
 * Two halves, and the estimate says which of them each number came from:
 *
 *  - the INPUT side is measured, not guessed. A per-term call re-sends the whole system prompt, and
 *    this app knows exactly how long its own prompts are — so the input estimate is the rendered
 *    prompt itself, at four characters per token. That also keeps the estimate honest as the prompt
 *    changes: edit a section and the estimate moves with it.
 *  - the OUTPUT side is whatever calls on that model have actually produced, averaged over the
 *    ledgers. With nothing logged it falls back to a stated default and SAYS it fell back, because
 *    an estimate whose provenance is hidden is a number nobody can argue with. Those logged calls
 *    mostly carried ten items at a time while this sweep asks for one, so the output side is an
 *    over-estimate too — named rather than corrected, because a correction factor invented here
 *    would be a guess wearing the clothes of a measurement.
 *
 * Two things it deliberately does not pretend to know. The vendor served 77.7% of the input tokens
 * of the A/B run out of its own prompt cache at a lower rate — a per-term sweep on one prompt is the
 * best possible case for that cache — so the live figure here is an UPPER bound. And the batch
 * figure is simply half: OpenAI's Batch API is 50% off for work that can wait, which a catalogue
 * sweep can. That path is not implemented; the number is printed so the decision to build it can be
 * taken against a figure rather than a feeling.
 */
final readonly class ShowcaseCostEstimator
{
    /** Characters per token — the rule of thumb for Latin+Cyrillic prose, good to ±15% at this size. */
    private const CHARS_PER_TOKEN = 4;

    /** Roughly what one term's data block adds to the prompt: the term, its key, its example. */
    private const DATA_TOKENS = 80;

    /** Fallbacks, used ONLY when no call on the model has ever been logged. Stated, never hidden. */
    private const DEFAULT_CORE_OUT = 150;

    private const DEFAULT_MECHANICS_OUT = 120;

    public function __construct(
        private PromptSource $prompts,
        private ObservedTokenAverages $observed,
        private GenerationStackConfig $stack,
        private ModelCost $cost = new ModelCost(),
    ) {}

    public function estimate(int $terms, bool $withMechanics = true): ShowcaseCostEstimate
    {
        $coreIn = $this->promptTokens($this->stack->corePromptVersion, PromptShape::Enrich);
        $mechanicsIn = $withMechanics
            ? $this->promptTokens($this->stack->mechanicsPromptVersion, PromptShape::Machinery)
            : 0;

        $coreObserved = $this->observed->perCall($this->stack->coreModel);
        $mechanicsObserved = $withMechanics ? $this->observed->perCall($this->stack->mechanicsModel) : null;

        $coreOut = $coreObserved[1] ?? self::DEFAULT_CORE_OUT;
        $mechanicsOut = $withMechanics ? ($mechanicsObserved[1] ?? self::DEFAULT_MECHANICS_OUT) : 0;

        $coreUsd = $this->cost->estimate($this->stack->coreModel, $coreIn * $terms, $coreOut * $terms);
        $mechanicsUsd = $withMechanics
            ? $this->cost->estimate($this->stack->mechanicsModel, $mechanicsIn * $terms, $mechanicsOut * $terms)
            : '0.000000';

        $total = $coreUsd === null || $mechanicsUsd === null
            ? null
            : number_format((float) $coreUsd + (float) $mechanicsUsd, 6, '.', '');

        return new ShowcaseCostEstimate(
            terms: $terms,
            coreTokensIn: $coreIn * $terms,
            coreTokensOut: $coreOut * $terms,
            mechanicsTokensIn: $mechanicsIn * $terms,
            mechanicsTokensOut: $mechanicsOut * $terms,
            coreUsd: $coreUsd,
            mechanicsUsd: $mechanicsUsd,
            totalUsd: $total,
            totalBatchUsd: $total !== null ? number_format((float) $total / 2, 6, '.', '') : null,
            source: $coreObserved !== null || $mechanicsObserved !== null
                ? 'вход — длина отрендеренного промпта; выход — среднее по залогированным вызовам, '
                    . 'а они несли по несколько items за раз, так что и выходная сторона завышена'
                : 'вход — длина отрендеренного промпта; выход — ДЕФОЛТ: вызовов на этих моделях в логах нет',
        );
    }

    /** The prompt as it will actually be sent, plus the per-term data block. */
    private function promptTokens(string $version, PromptShape $shape): int
    {
        $rendered = $this->prompts->render($version, $shape, [
            'source_lang' => 'Russian',
            'target_lang' => 'English',
            'levels' => 'A1, A2, B1, B2, C1, C2',
            'size' => '1',
        ]);

        return (int) ceil(mb_strlen($rendered->text) / self::CHARS_PER_TOKEN) + self::DATA_TOKENS;
    }
}
