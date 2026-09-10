<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Exception;

use App\Modules\Plan\Domain\ValueObject\PlanSceneId;

/** The scene the goal is really about (priority 1) is never removed — shorten the plan instead. */
final class CoreSceneNotRemovable extends PlanProblem
{
    public static function withId(PlanSceneId $id): self
    {
        return new self("The core scene cannot be removed: {$id->value}", ['scene_id' => $id->value]);
    }

    public function problemStatus(): int
    {
        return 409;
    }

    public function problemCode(): string
    {
        return 'plan_core_scene';
    }

    public function problemTitle(): string
    {
        return 'The core scene cannot be removed';
    }
}
