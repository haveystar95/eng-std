<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto;

/**
 * One stage row of the window; the count and the minutes left are there for the current stage only. The talk's row
 * also carries its entry title («Поговори с врачом») and the number of scenes it walks (наряд CONV-2, п. 12), and the
 * targets its talk is for (наряд BACK-TAILS-2 §4) — null on every card row. Every row carries its planned minutes.
 */
final readonly class WindowStageView
{
    /**
     * @param  list<array{scene_id: string, ref: string, text_target: string, text_native: string, said: bool}>|null  $targets  the talk's row only
     */
    public function __construct(
        public string $stage,
        public string $state,
        public ?int $doneCount,
        public ?int $total,
        public ?int $minutesLeft,
        public float $share,
        public ?string $talkTitleNative = null,
        public ?int $scenesCount = null,
        public int $minutes = 0,
        public ?array $targets = null,
    ) {}
}
