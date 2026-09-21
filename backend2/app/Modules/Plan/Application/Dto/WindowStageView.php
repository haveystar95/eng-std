<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto;

/**
 * One stage row of the window; the count and the minutes left are there for the current stage only. The talk's row
 * also carries its entry title («Поговори с врачом») and the number of scenes it walks (наряд CONV-2, п. 12) — null on
 * every card row.
 */
final readonly class WindowStageView
{
    public function __construct(
        public string $stage,
        public string $state,
        public ?int $doneCount,
        public ?int $total,
        public ?int $minutesLeft,
        public float $share,
        public ?string $talkTitleNative = null,
        public ?int $scenesCount = null,
    ) {}
}
