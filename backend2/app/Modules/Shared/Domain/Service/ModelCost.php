<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Service;

/**
 * Estimates the USD cost of a model call from its token counts. ONE pricing table for the whole
 * app so cost is computed the same way everywhere: Generation prices the spend it records in its
 * ledgers, and Admin prices an individual outbound call read back out of the request log. It lives
 * in the Shared kernel for that reason — a second copy of these rates is how two screens start
 * disagreeing about what a collection cost.
 *
 * Rates are USD per 1K tokens [input, output]; an unknown model or missing counts → null.
 */
final class ModelCost
{
    /**
     * Text-model rates, USD per 1K tokens [input, output].
     *
     * A model that is not here prices as null — "unpriced", never as free. A bake-off that showed a
     * missing rate as $0 would hand the cheapest-looking column to whichever vendor nobody had
     * entered yet, which is the one mistake this table exists to prevent.
     *
     * Rates checked against each vendor's own pricing page on 2026-08-20, per 1M and divided by
     * 1000 here. Re-check when a vendor announces a change: this is a cost ESTIMATE from token
     * counts, not an invoice.
     *
     * Two things that make a naive cross-vendor comparison wrong, and are NOT corrected for here
     * because correcting for them silently would hide them:
     *
     *  - **xAI prices in two tiers.** The rates below hold under 200K input tokens and double above
     *    it. Only the low tier is entered: every call this app makes is a few thousand tokens, and a
     *    request that crossed 200K would be a bug on its own account.
     *  - **Claude 4.7 and later use a newer tokenizer** that produces roughly 30% more tokens for
     *    the same text. Its lower per-token price partly reflects that, so comparing $/token across
     *    vendors flatters Claude and comparing token COUNTS flatters everyone else. Compare the
     *    dollar total for the same task, which is what the bake-off actually reports.
     *
     * @var array<string, array{0: float, 1: float}>
     */
    private const PRICING = [
        // OpenAI. gpt-4o/4o-mini are the previous generation and still serve production here.
        'gpt-4o' => [0.0025, 0.01],
        'gpt-4o-mini' => [0.00015, 0.0006],
        'gpt-5.6-sol' => [0.005, 0.03],
        'gpt-5.6-terra' => [0.002, 0.012],
        'gpt-5.6-luna' => [0.0002, 0.0012],
        'gpt-5.5' => [0.005, 0.03],
        'gpt-5.4' => [0.0025, 0.015],
        'gpt-5.4-mini' => [0.00075, 0.0045],
        'gpt-5.4-nano' => [0.0002, 0.00125],
        // Anthropic (Claude). Sonnet 5 is $2/$10: the increase to $3/$15 once scheduled for
        // 2026-09-01 was cancelled and the introductory price became the standard one.
        'claude-opus-5' => [0.005, 0.025],
        'claude-sonnet-5' => [0.002, 0.01],
        'claude-sonnet-4-6' => [0.003, 0.015],
        'claude-haiku-4-5' => [0.001, 0.005],
        // xAI (Grok).
        'grok-4.6' => [0.002, 0.006],
        'grok-4.5' => [0.002, 0.006],
        'grok-4.3' => [0.00125, 0.0025],
        'grok-build-0.1' => [0.001, 0.002],
        // Google (Gemini). The 3.7/3.6 Flash rates are promotional through 2026-12-31.
        'gemini-3.7-flash' => [0.00075, 0.00375],
        'gemini-3.6-flash' => [0.00075, 0.00375],
        'gemini-3.5-flash' => [0.0015, 0.009],
        'gemini-3.5-flash-lite' => [0.0003, 0.0025],
        'gemini-2.5-flash' => [0.0003, 0.0025],
        'gemini-2.5-flash-lite' => [0.0001, 0.0004],
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
        $key = self::baseModel($model);

        if (! isset(self::PRICING[$key]) || $tokensIn === null || $tokensOut === null) {
            return null;
        }

        [$inRate, $outRate] = self::PRICING[$key];
        $cost = ($tokensIn / 1000) * $inRate + ($tokensOut / 1000) * $outRate;

        return number_format($cost, 6, '.', '');
    }

    /**
     * Strip the dated snapshot suffix: a response says `gpt-4o-mini-2024-07-18`, we price
     * `gpt-4o-mini`. Without this, every call read back out of the request log came out unpriced —
     * what we ASK for and what the API says it USED are different strings.
     */
    public static function baseModel(string $model): string
    {
        return (string) preg_replace('/-\d{4}-\d{2}-\d{2}$/', '', $model);
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
        $key = self::baseModel($model);

        if (! isset(self::REALTIME_PRICING[$key])) {
            return null;
        }

        [$audioInPer1M, $audioOutPer1M, $inTokPerSec, $outTokPerSec, $textInRate, $textOutRate] = self::REALTIME_PRICING[$key];

        $audioInTokens = max(0, $inputAudioSeconds) * $inTokPerSec;
        $audioOutTokens = max(0, $outputAudioSeconds) * $outTokPerSec;

        $cost = ($audioInTokens / 1_000_000) * $audioInPer1M
            + ($audioOutTokens / 1_000_000) * $audioOutPer1M
            + (max(0, $promptTextTokens) / 1000) * $textInRate
            + (max(0, $completionTextTokens) / 1000) * $textOutRate;

        return number_format($cost, 6, '.', '');
    }
}
