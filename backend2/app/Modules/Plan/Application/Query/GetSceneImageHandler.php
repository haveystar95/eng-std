<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Query;

use App\Modules\Plan\Application\Dto\SceneImageFile;
use App\Modules\Plan\Application\Exception\SceneImageUnavailable;
use App\Modules\Plan\Application\Port\SceneImageStore;
use App\Modules\Plan\Application\Port\SceneLocator;
use App\Modules\Plan\Domain\Exception\SceneImageNotFound;

/**
 * A square copy of the scene photo: the stored file, or — for a scene photographed before the
 * copies were kept, or whose copy the photo job could not fetch — fetched now, stored and served.
 * The endpoint heals itself; nothing has to be backfilled for a plan to show its circles.
 *
 * A scene that is not the learner's and a scene without a photo are the same 404.
 */
final readonly class GetSceneImageHandler
{
    public function __construct(
        private SceneLocator $scenes,
        private SceneImageStore $store,
    ) {}

    public function __invoke(GetSceneImage $query): SceneImageFile
    {
        $image = $this->scenes->ownedImage($query->sceneId, $query->actorId);
        if ($image === null) {
            throw SceneImageNotFound::forScene($query->sceneId);
        }

        $bytes = $this->store->read($query->sceneId, $query->size)
            ?? $this->store->fetch($query->sceneId, $image->url, $query->size)
            ?? throw SceneImageUnavailable::forScene($query->sceneId, $query->size->value);

        return new SceneImageFile($bytes, sha1($bytes));
    }
}
