<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Exception;

use App\Modules\Plan\Domain\ValueObject\PlanSceneId;

final class SceneNotFound extends PlanProblem
{
    public static function withId(PlanSceneId $id): self
    {
        return new self("Scene not found: {$id->value}", ['scene_id' => $id->value]);
    }

    public function problemStatus(): int
    {
        return 404;
    }

    public function problemCode(): string
    {
        return 'plan_scene_not_found';
    }

    public function problemTitle(): string
    {
        return 'Scene not found';
    }
}
