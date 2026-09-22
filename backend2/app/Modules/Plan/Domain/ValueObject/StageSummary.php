<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\ValueObject;

/**
 * THE NUMBERS OF ONE STAGE'S SUMMARY (кадр 30-6, наряд FIX-3 §10) — counted by the server, printed by the phone: how
 * many units the stage has (`total`) and how many of them are walked (`done`), how many were passed at the first attempt
 * without a hint (`firstTry`), and how many come back tomorrow (`returns`). What a unit is depends on the stage
 * ({@see \App\Modules\Plan\Domain\Service\StageSummaries}).
 */
final readonly class StageSummary
{
    public function __construct(
        public int $done,
        public int $total,
        public int $firstTry,
        public int $returns,
    ) {}
}
