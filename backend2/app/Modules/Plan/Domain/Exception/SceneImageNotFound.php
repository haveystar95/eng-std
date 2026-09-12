<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Exception;

use App\Modules\Plan\Domain\ValueObject\PlanSceneId;

/** The scene is not the learner's, or it has no photo — the API says the same for both. */
final class SceneImageNotFound extends PlanProblem
{
    public static function forScene(PlanSceneId $id): self
    {
        return new self("Scene image not found: {$id->value}", ['scene_id' => $id->value]);
    }

    public function problemStatus(): int
    {
        return 404;
    }

    public function problemCode(): string
    {
        return 'plan_scene_image_not_found';
    }

    public function problemTitle(): string
    {
        return 'Scene image not found';
    }
}
