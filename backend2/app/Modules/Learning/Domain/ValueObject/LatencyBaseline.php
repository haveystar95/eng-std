<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\ValueObject;

/**
 * The user's personal median answer time for one exercise mode, over CORRECT answers only
 * (wrong answers carry deliberation and thrashing that would drag the threshold around).
 * People vary threefold, and typing is far slower than multiple choice, so "slow"/"fast" are
 * relative to this per-mode median — until there are enough samples, when it is unknown and
 * the grader falls back to absolute defaults.
 */
final readonly class LatencyBaseline
{
    /** Below this many correct samples the median is too noisy to trust. */
    public const MIN_SAMPLES = 20;

    private function __construct(public ?int $medianMs) {}

    /** Not enough correct answers yet in this mode — grade speed by absolute defaults. */
    public static function insufficient(): self
    {
        return new self(null);
    }

    public static function median(int $medianMs): self
    {
        return new self(max(1, $medianMs));
    }

    /**
     * Build from a sample of correct-answer latencies for one mode: a median once there are
     * enough, otherwise "insufficient".
     *
     * @param  list<int>  $correctLatenciesMs
     */
    public static function fromCorrectSamples(array $correctLatenciesMs): self
    {
        if (count($correctLatenciesMs) < self::MIN_SAMPLES) {
            return self::insufficient();
        }

        sort($correctLatenciesMs);
        $count = count($correctLatenciesMs);
        $mid = intdiv($count, 2);
        $median = $count % 2 === 0
            ? intdiv($correctLatenciesMs[$mid - 1] + $correctLatenciesMs[$mid], 2)
            : $correctLatenciesMs[$mid];

        return self::median($median);
    }

    public function isKnown(): bool
    {
        return $this->medianMs !== null;
    }
}
