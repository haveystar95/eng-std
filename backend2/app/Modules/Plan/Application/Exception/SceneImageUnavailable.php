<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Exception;

use App\Modules\Plan\Domain\Exception\PlanProblem;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;

/**
 * The scene has a photo, no sized copy is stored yet, and the vendor did not hand one over just
 * now. Transient by nature — a later request fetches it — so it is a 503, not a 404 the client
 * might remember.
 */
final class SceneImageUnavailable extends PlanProblem
{
    public static function forScene(PlanSceneId $id, int $size): self
    {
        return new self("Scene image could not be fetched: {$id->value}/{$size}", ['scene_id' => $id->value, 'size' => $size]);
    }

    public function problemStatus(): int
    {
        return 503;
    }

    public function problemCode(): string
    {
        return 'plan_scene_image_unavailable';
    }

    public function problemTitle(): string
    {
        return 'Scene image unavailable';
    }
}
