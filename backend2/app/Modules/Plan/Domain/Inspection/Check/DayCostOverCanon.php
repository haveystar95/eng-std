<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Inspection\Check;

use App\Modules\Plan\Domain\Inspection\PlanCheck;
use App\Modules\Plan\Domain\Inspection\PlanFacts;
use App\Modules\Plan\Domain\Inspection\PlanIssue;

/**
 * ЦЕНА ДНЯ ВЫШЕ КАНОНА (наряд ADM-1 и его доработка): a day ≈ $0.16 — generation ≈ $0.08 plus voice ≈ $0.08 — is a
 * canon with room: from 125 % of it ($0.20) the day is a warning, from 150 % ($0.24) an error; $0.1648 is no finding.
 * The repairs are at most 10 % of the day's generation (DECISIONS п. 323: «починки ≤ 10 % цены дня (урок + судья)») —
 * a warning past that, as before. The canon and its thresholds come in from config. A day whose repairs the journal
 * cannot say exactly is not judged on the share.
 */
final readonly class DayCostOverCanon implements PlanCheck
{
    public const CODE = 'day_cost_over_canon';

    public function __construct(
        private float $dayUsd,
        private float $repairShare,
        private float $warnRatio = 1.25,
        private float $errorRatio = 1.5,
    ) {}

    public function code(): string
    {
        return self::CODE;
    }

    public function find(PlanFacts $facts): array
    {
        $out = [];
        foreach ($facts->days as $day) {
            if ($day->generationUsd === null) {
                continue;
            }
            $total = $day->generationUsd + $day->voiceUsd;
            $severity = match (true) {
                $total >= $this->dayUsd * $this->errorRatio => PlanIssue::ERROR,
                $total >= $this->dayUsd * $this->warnRatio => PlanIssue::WARNING,
                default => null,
            };
            if ($severity !== null) {
                $out[] = new PlanIssue(self::CODE, $severity, $day->number, 'day', (string) $day->number,
                    sprintf('День %d стоит $%.4f — %d %% канона $%.2f (генерация $%.4f + голос $%.4f)', $day->number, $total, (int) round(100 * $total / $this->dayUsd), $this->dayUsd, $day->generationUsd, $day->voiceUsd),
                    ['total_usd' => round($total, 6), 'canon_usd' => $this->dayUsd, 'ratio' => round($total / $this->dayUsd, 4)],
                );
            }
            $lessonAndJudge = $day->repairUsd === null ? 0.0 : $day->generationUsd - $day->repairUsd;
            if ($day->repairUsd !== null && $lessonAndJudge > 0.0 && $day->repairUsd / $lessonAndJudge > $this->repairShare) {
                $out[] = new PlanIssue(self::CODE, PlanIssue::WARNING, $day->number, 'day', (string) $day->number,
                    sprintf('День %d: починки P2R $%.4f — %.1f %% цены дня, канон ≤ %.0f %%', $day->number, $day->repairUsd, 100 * $day->repairUsd / $lessonAndJudge, 100 * $this->repairShare),
                    ['repair_usd' => round($day->repairUsd, 6), 'share' => round($day->repairUsd / $lessonAndJudge, 4)],
                );
            }
        }

        return $out;
    }
}
