<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Service;

use App\Modules\Generation\Application\Dto\CostEstimate;
use App\Modules\Generation\Domain\Service\ModelCost;
use App\Modules\Generation\Domain\ValueObject\TranscriptLine;

/**
 * Estimates a realtime dialog's spend from how long it ran plus the transcript we saw. The user's
 * lines are the model's audio input (tokens_in), the assistant's are its output (tokens_out); text
 * tokens are approximated at ~4 chars/token.
 *
 * The audio split is the estimate's core assumption: INPUT audio runs the full session (the mic
 * streams continuously), while OUTPUT audio is billed only while the agent actually spoke. The agent
 * doesn't emit a speaking-duration we can read, and the transcript timestamps are unreliable for it
 * (the gaps between assistant lines include the learner's turns and silence), so we APPROXIMATE the
 * agent's speaking seconds from the length of its transcript at a natural speech rate (~150 wpm ≈
 * {@see SPOKEN_CHARS_PER_SEC} chars/sec), capped at the billed session. Explicitly an estimate — the
 * realtime usage event never reaches us.
 */
final readonly class PracticeCostEstimator
{
    private const CHARS_PER_TOKEN = 4;

    /** ~150 words/min × ~6 chars/word ÷ 60 ≈ 15 chars of speech per second. */
    private const SPOKEN_CHARS_PER_SEC = 15;

    public function __construct(private ModelCost $cost) {}

    /**
     * @param  array<string, mixed>  $lesson
     * @param  list<TranscriptLine>  $lines
     */
    public function estimate(array $lesson, array $lines, int $durationSeconds): CostEstimate
    {
        $model = is_string($lesson['model'] ?? null) ? $lesson['model'] : '';

        $userChars = 0;
        $assistantChars = 0;
        foreach ($lines as $line) {
            if ($line->role->isUser()) {
                $userChars += mb_strlen($line->text);
            } else {
                $assistantChars += mb_strlen($line->text);
            }
        }

        $tokensIn = intdiv($userChars, self::CHARS_PER_TOKEN);
        $tokensOut = intdiv($assistantChars, self::CHARS_PER_TOKEN);

        // Input audio = the whole session; output audio = the agent's approximate speaking time,
        // derived from how much it said, never more than the session lasted.
        $inputAudioSeconds = max(0, $durationSeconds);
        $agentSpeakingSeconds = min($inputAudioSeconds, intdiv($assistantChars, self::SPOKEN_CHARS_PER_SEC));

        return new CostEstimate(
            costUsd: $this->cost->estimateRealtime($model, $inputAudioSeconds, $agentSpeakingSeconds, $tokensIn, $tokensOut),
            tokensIn: $tokensIn,
            tokensOut: $tokensOut,
        );
    }
}
