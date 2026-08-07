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
     * Realtime (voice) pricing, per model:
     *   [audio-in USD/1M tokens, audio-out USD/1M tokens, audio-in tokens/sec, audio-out tokens/sec,
     *    text-in USD/1K, text-out USD/1K].
     * Audio dominates; the text side is the tiny transcript we relay. The tokens/sec figures are how
     * each vendor tokenises audio — OpenAI: 1 token/100ms input, 1 token/50ms output (10/20 per sec);
     * Gemini: 25 tokens/sec each way. A realtime session's true cost is only known from a usage event
     * we don't see server-side, so {@see estimateRealtime} is deliberately an ESTIMATE.
     *
     * Sources: OpenAI realtime audio-token rates; Gemini Live pricing ($3/$12 per 1M, 25 tok/sec).
     *
     * @var array<string, array{0: float, 1: float, 2: int, 3: int, 4: float, 5: float}>
     */
    private const REALTIME_PRICING = [
        // OpenAI 2.1-mini: $10 / $20 per 1M audio in/out tokens.
        'gpt-realtime-2.1-mini' => [10.0, 20.0, 10, 20, 0.0006, 0.0024],
        // gpt-realtime-mini is deprecated but may appear on older stored dialogs — same rates.
        'gpt-realtime-mini' => [10.0, 20.0, 10, 20, 0.0006, 0.0024],
        // OpenAI full 2.1: $32 / $64 per 1M audio in/out tokens.
        'gpt-realtime-2.1' => [32.0, 64.0, 10, 20, 0.004, 0.016],
        'gpt-realtime' => [32.0, 64.0, 10, 20, 0.004, 0.016],
        // Gemini Live (flash): $3 / $12 per 1M audio in/out tokens, 25 audio tokens/sec each way.
        'gemini-3.1-flash-live-preview' => [3.0, 12.0, 25, 25, 0.0003, 0.0025],
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
     * Estimate a realtime session's spend: active audio seconds → audio tokens at OpenAI's
     * documented per-second rates, priced per 1M; plus the small text transcript we relayed. Input
     * and output audio seconds are supplied separately (input = full session, output = the agent's
     * actual speaking time). Unknown model → null. The result is an estimate by construction.
     */
    public function estimateRealtime(
        string $model,
        int $inputAudioSeconds,
        int $outputAudioSeconds,
        int $promptTextTokens,
        int $completionTextTokens,
    ): ?string {
        if (! isset(self::REALTIME_PRICING[$model])) {
            return null;
        }

        [$audioInPer1M, $audioOutPer1M, $inTokPerSec, $outTokPerSec, $textInRate, $textOutRate] = self::REALTIME_PRICING[$model];

        $audioInTokens = max(0, $inputAudioSeconds) * $inTokPerSec;
        $audioOutTokens = max(0, $outputAudioSeconds) * $outTokPerSec;

        $cost = ($audioInTokens / 1_000_000) * $audioInPer1M
            + ($audioOutTokens / 1_000_000) * $audioOutPer1M
            + (max(0, $promptTextTokens) / 1000) * $textInRate
            + (max(0, $completionTextTokens) / 1000) * $textOutRate;

        return number_format($cost, 6, '.', '');
    }
}
