<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Service;

use App\Modules\Generation\Application\Dto\CostEstimate;
use App\Modules\Generation\Domain\Service\ModelCost;
use App\Modules\Generation\Domain\ValueObject\TranscriptLine;

/**
 * Estimates a realtime dialog's spend from how long it ran plus the transcript we saw. The user's
 * lines are the model's audio input (tokens_in), the assistant's are its output (tokens_out); text
 * tokens are approximated at ~4 chars/token. Audio time dominates and is priced per minute in
 * {@see ModelCost}. The result is explicitly an estimate — the realtime usage event never reaches us.
 */
final readonly class PracticeCostEstimator
{
    private const CHARS_PER_TOKEN = 4;

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

        return new CostEstimate(
            costUsd: $this->cost->estimateRealtime($model, $durationSeconds, $tokensIn, $tokensOut),
            tokensIn: $tokensIn,
            tokensOut: $tokensOut,
        );
    }
}
