<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Exception;

use RuntimeException;

/**
 * THE CREDITS CAP OF ONE RUN IS REACHED (TTS-2, the architect's decision): the scene next in line would take the run of
 * purchases — one voice job, one `plan:speak-backfill` — past `generation.speech.job_credits_cap`, so nothing of it is
 * bought. The job that meets it writes the letter to the log and ends; the backfill stops and says so. What is not bought
 * stays owed, and the day reads those lines with the phone's voice.
 */
final class VoiceCapReached extends RuntimeException
{
    private function __construct(
        string $message,
        public readonly int $sceneCredits,
        public readonly int $spent,
        public readonly int $cap,
    ) {
        parent::__construct($message);
    }

    public static function before(int $sceneCredits, int $spent, int $cap): self
    {
        return new self(sprintf(
            'credits cap: this run has bought %d credits, the next scene would take about %d more, the cap is %d',
            $spent,
            $sceneCredits,
            $cap,
        ), $sceneCredits, $spent, $cap);
    }
}
