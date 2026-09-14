<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Command;

use App\Modules\Plan\Domain\Lesson\Lesson;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;

/**
 * Write a repaired answer into its scene (P2R `--apply`): the answer, the validator's findings over it and
 * what the repair cost.
 */
final readonly class ReviseLesson
{
    /** @param list<array{code: string, address: string, detail: string}> $findings */
    public function __construct(
        public PlanSceneId $sceneId,
        public Lesson $answer,
        public array $findings,
        public string $repairCostUsd,
    ) {}
}
