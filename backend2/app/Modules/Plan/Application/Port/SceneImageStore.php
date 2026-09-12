<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Port;

use App\Modules\Plan\Domain\ValueObject\PlanSceneId;
use App\Modules\Plan\Domain\ValueObject\SceneImageSize;

/**
 * The square copies of a scene's photo, kept by the server so the client gets them from one
 * address with an honest `immutable` — bytes on a disk, one file per (scene, size), written once.
 *
 * `fetch()` is the only outbound call: it asks the photo's CDN for the crop, stores what came back
 * and returns it. It never throws — a copy that could not be fetched is a null, and the next
 * request for it tries again.
 */
interface SceneImageStore
{
    public function has(PlanSceneId $sceneId, SceneImageSize $size): bool;

    public function read(PlanSceneId $sceneId, SceneImageSize $size): ?string;

    public function fetch(PlanSceneId $sceneId, string $sourceUrl, SceneImageSize $size): ?string;
}
