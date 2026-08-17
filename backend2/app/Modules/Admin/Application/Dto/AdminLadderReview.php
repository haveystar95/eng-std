<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Dto;

/**
 * The governing (latest) review of one (user, term) pair.
 *
 * "Latest" is by `client_seq`, not by `answered_at`: the device clock is not the ordering truth
 * anywhere in this system, and a lagging phone would otherwise make an older answer look like the
 * current verdict. `answeredAt` is carried for display only.
 *
 * `ladderStep` is the rung the CARD WAS DEALT AT, which is not the pair's rung now — folding the
 * answer may have moved it. That is exactly what makes the column worth showing: it says what the
 * learner was actually asked.
 */
final readonly class AdminLadderReview
{
    public function __construct(
        public string $id,
        public ?string $exerciseMode,
        public string $grade,
        public ?bool $isCorrect,
        public bool $isPractice,
        public ?int $ladderStep,
        public ?string $response,
        public int $clientSeq,
        public ?string $answeredAt,
    ) {}
}
