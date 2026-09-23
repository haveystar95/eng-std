<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Inspection;

use DateTimeImmutable;

/**
 * THE CANON THE PLAN PAGE HOLDS A PLAN TO (наряд ADM-1 и доработка; `plan.inspection` in config): a day ≈ $0.16 —
 * generation ≈ $0.08 + voice ≈ $0.08 — a warning from 125 % of it, an error from 150 %; the repairs at most 10 % of the
 * day's generation (DECISIONS п. 323); and the moment the role's openings (`opens_target`) began to be recorded — talks
 * begun before it are not judged on them.
 */
final readonly class InspectionCanon
{
    public function __construct(
        public float $dayUsd,
        public float $generationUsd,
        public float $voiceUsd,
        public float $repairShare,
        public float $warnRatio = 1.25,
        public float $errorRatio = 1.5,
        public DateTimeImmutable $openersSince = new DateTimeImmutable('2026-09-23T00:00:00+00:00'),
    ) {}

    /** @return array<string, float|string> */
    public function toArray(): array
    {
        return [
            'day_usd' => $this->dayUsd,
            'generation_usd' => $this->generationUsd,
            'voice_usd' => $this->voiceUsd,
            'repair_share' => $this->repairShare,
            'warn_ratio' => $this->warnRatio,
            'error_ratio' => $this->errorRatio,
            'openers_since' => $this->openersSince->format(DATE_ATOM),
        ];
    }
}
