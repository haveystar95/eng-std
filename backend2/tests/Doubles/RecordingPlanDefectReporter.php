<?php

declare(strict_types=1);

namespace Tests\Doubles;

use App\Modules\Generation\Application\Port\PlanDefectReporter;

/**
 * Keeps what it was told, so «logged on every attempt, counted only on the kept one» is an
 * assertion rather than something read off the log by hand.
 *
 * One double for both plan prompts, because there is one port. The counter side deliberately
 * counts only what arrived with `counted: true` — the production adapter increments nothing
 * otherwise, and a double that counted everything would let that rule rot unnoticed.
 */
final class RecordingPlanDefectReporter implements PlanDefectReporter
{
    /** @var list<array{plan_id: string, day_index: int|null, counter: string, detail: string, counted: bool}> */
    public array $reported = [];

    /** @var list<array{text: string, reason: string}> */
    public array $droppedHints = [];

    public function transliterationDropped(
        string $planId,
        int $dayIndex,
        string $text,
        ?string $raw,
        string $reason,
    ): void {
        $this->droppedHints[] = ['text' => $text, 'reason' => $reason];
    }

    public function droppedTransliterations(): int
    {
        return count($this->droppedHints);
    }

    public function warned(
        string $planId,
        ?int $dayIndex,
        string $counter,
        string $detail,
        bool $counted,
    ): void {
        $this->reported[] = [
            'plan_id' => $planId,
            'day_index' => $dayIndex,
            'counter' => $counter,
            'detail' => $detail,
            'counted' => $counted,
        ];
    }

    public function warnings(string $counter): int
    {
        return count(array_filter(
            $this->reported,
            static fn (array $w): bool => $w['counter'] === $counter && $w['counted'],
        ));
    }

    /** Every counter that was REPORTED, whether or not it was counted. */
    public function reportedCounters(): array
    {
        return array_values(array_unique(array_column($this->reported, 'counter')));
    }
}
