<?php

declare(strict_types=1);

namespace App\Modules\Generation\Domain\Service;

/**
 * Estimates the USD cost of a model call from its token counts. One pricing table for every AI
 * spend in the module (collection generation, term enrichment) so cost is computed the same way
 * everywhere. Rates are USD per 1K tokens [input, output]; an unknown model or missing counts → null.
 */
final class ModelCost
{
    /** @var array<string, array{0: float, 1: float}> */
    private const PRICING = [
        'gpt-4o' => [0.0025, 0.01],
        'gpt-4o-mini' => [0.00015, 0.0006],
    ];

    /**
     * Realtime (voice) pricing: [audio USD per minute (input+output blended), text-in USD/1K,
     * text-out USD/1K]. Audio dominates; the text side is the tiny transcript we relay. All rates
     * are approximate — a realtime session's true cost is only known from the usage event we don't
     * see server-side, so {@see estimateRealtime} is deliberately an ESTIMATE.
     *
     * @var array<string, array{0: float, 1: float, 2: float}>
     */
    private const REALTIME_PRICING = [
        'gpt-realtime-mini' => [0.05, 0.0006, 0.0024],
        'gpt-realtime' => [0.20, 0.004, 0.016],
    ];

    public function estimate(string $model, ?int $tokensIn, ?int $tokensOut): ?string
    {
        if (! isset(self::PRICING[$model]) || $tokensIn === null || $tokensOut === null) {
            return null;
        }

        [$inRate, $outRate] = self::PRICING[$model];
        $cost = ($tokensIn / 1000) * $inRate + ($tokensOut / 1000) * $outRate;

        return number_format($cost, 6, '.', '');
    }

    /**
     * Estimate a realtime session's spend from its billed audio time plus the transcript text we
     * saw. Unknown model → null (no fabricated cost). The result is an estimate by construction.
     */
    public function estimateRealtime(
        string $model,
        int $durationSeconds,
        int $promptTextTokens,
        int $completionTextTokens,
    ): ?string {
        if (! isset(self::REALTIME_PRICING[$model])) {
            return null;
        }

        [$audioPerMin, $textInRate, $textOutRate] = self::REALTIME_PRICING[$model];
        $cost = (max(0, $durationSeconds) / 60) * $audioPerMin
            + (max(0, $promptTextTokens) / 1000) * $textInRate
            + (max(0, $completionTextTokens) / 1000) * $textOutRate;

        return number_format($cost, 6, '.', '');
    }
}
