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
     * Realtime (voice) pricing, per model: [audio-in USD/1M tokens, audio-out USD/1M tokens,
     * text-in USD/1K, text-out USD/1K]. Audio dominates; the text side is the tiny transcript we
     * relay. A realtime session's true cost is only known from a usage event we don't see
     * server-side, so {@see estimateRealtime} converts billed seconds to audio tokens at OpenAI's
     * documented rates and is deliberately an ESTIMATE.
     *
     * @var array<string, array{0: float, 1: float, 2: float, 3: float}>
     */
    private const REALTIME_PRICING = [
        // 2.1-mini: $10 / $20 per 1M audio in/out tokens.
        'gpt-realtime-2.1-mini' => [10.0, 20.0, 0.0006, 0.0024],
        // gpt-realtime-mini is deprecated but may appear on older stored dialogs — same rates.
        'gpt-realtime-mini' => [10.0, 20.0, 0.0006, 0.0024],
        // Full 2.1: $32 / $64 per 1M audio in/out tokens.
        'gpt-realtime-2.1' => [32.0, 64.0, 0.004, 0.016],
        'gpt-realtime' => [32.0, 64.0, 0.004, 0.016],
    ];

    /**
     * OpenAI tokenises realtime audio at 1 token / 100 ms of user (input) audio and 1 token / 50 ms
     * of assistant (output) audio — i.e. 10 input and 20 output audio tokens per second. The caller
     * passes how many seconds each stream was active: input runs the whole session (the mic streams
     * continuously), output only while the agent actually spoke — measuring output against the full
     * session overcounts, since the agent is silent for the learner's turns.
     */
    private const AUDIO_INPUT_TOKENS_PER_SEC = 10;
    private const AUDIO_OUTPUT_TOKENS_PER_SEC = 20;

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

        [$audioInPer1M, $audioOutPer1M, $textInRate, $textOutRate] = self::REALTIME_PRICING[$model];

        $audioInTokens = max(0, $inputAudioSeconds) * self::AUDIO_INPUT_TOKENS_PER_SEC;
        $audioOutTokens = max(0, $outputAudioSeconds) * self::AUDIO_OUTPUT_TOKENS_PER_SEC;

        $cost = ($audioInTokens / 1_000_000) * $audioInPer1M
            + ($audioOutTokens / 1_000_000) * $audioOutPer1M
            + (max(0, $promptTextTokens) / 1000) * $textInRate
            + (max(0, $completionTextTokens) / 1000) * $textOutRate;

        return number_format($cost, 6, '.', '');
    }
}
