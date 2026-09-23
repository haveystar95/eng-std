<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Inspection;

/**
 * THE MONEY CANON THE PLAN PAGE HOLDS A DAY TO (наряд ADM-1; `plan.inspection` in config): a day ≈ $0.16 — generation
 * ≈ $0.08 + voice ≈ $0.08 — and the repairs at most 10 % of the day's generation (DECISIONS п. 323).
 */
final readonly class InspectionCanon
{
    public function __construct(
        public float $dayUsd,
        public float $generationUsd,
        public float $voiceUsd,
        public float $repairShare,
    ) {}

    /** @return array<string, float> */
    public function toArray(): array
    {
        return ['day_usd' => $this->dayUsd, 'generation_usd' => $this->generationUsd, 'voice_usd' => $this->voiceUsd, 'repair_share' => $this->repairShare];
    }
}
