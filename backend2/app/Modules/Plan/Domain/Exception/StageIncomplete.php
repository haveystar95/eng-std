<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Exception;

use App\Modules\Plan\Domain\ValueObject\Stage;

/** A stage (or the day) closes only when every card in it has been answered. */
final class StageIncomplete extends PlanProblem
{
    public static function stage(Stage $stage, int $remaining): self
    {
        return new self(
            "Stage «{$stage->value}» still has {$remaining} unanswered cards.",
            ['stage' => $stage->value, 'remaining' => $remaining],
        );
    }

    public function problemStatus(): int
    {
        return 409;
    }

    public function problemCode(): string
    {
        return 'plan_stage_incomplete';
    }

    public function problemTitle(): string
    {
        return 'The stage is not finished';
    }
}
