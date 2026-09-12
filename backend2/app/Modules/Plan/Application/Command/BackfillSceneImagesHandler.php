<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Command;

use App\Modules\Plan\Application\Dto\SceneImageBackfillReport;
use App\Modules\Plan\Application\Port\PlanImageFinder;
use App\Modules\Plan\Application\Port\SceneImageStore;
use App\Modules\Plan\Application\Port\SceneLocator;
use App\Modules\Plan\Domain\Repository\PlanRepository;
use App\Modules\Plan\Domain\ValueObject\SceneImageSize;

/**
 * The scenes photographed before tones and sized copies were kept get both. Idempotent: a scene
 * with a tone is not asked again, a copy on the disk is not fetched again, and the tone write is
 * conditional on the scene still having no tone and still having the same photo. A run that the
 * vendor cut short (a rate limit, a timeout) is simply run again.
 *
 * Returns the run's counts — the console prints them; nothing else reads them.
 */
final readonly class BackfillSceneImagesHandler
{
    public function __construct(
        private SceneLocator $scenes,
        private PlanRepository $plans,
        private PlanImageFinder $images,
        private SceneImageStore $store,
    ) {}

    public function __invoke(BackfillSceneImages $command): SceneImageBackfillReport
    {
        $scenes = 0;
        $tonesWritten = 0;
        $tonesMissing = 0;
        $copiesFetched = 0;
        $copiesMissing = 0;

        foreach ($this->scenes->scenesWithImages($command->planId) as $ref) {
            $scenes++;
            if ($ref->image->tone === null) {
                $tone = $this->images->tone($ref->image->url);
                if ($tone !== null && $this->plans->attachSceneImageTone($ref->sceneId, $ref->image->url, $tone)) {
                    $tonesWritten++;
                } else {
                    $tonesMissing++;
                }
            }
            foreach (SceneImageSize::cases() as $size) {
                if ($this->store->has($ref->sceneId, $size)) {
                    continue;
                }
                if ($this->store->fetch($ref->sceneId, $ref->image->url, $size) !== null) {
                    $copiesFetched++;
                } else {
                    $copiesMissing++;
                }
            }
        }

        return new SceneImageBackfillReport($scenes, $tonesWritten, $tonesMissing, $copiesFetched, $copiesMissing);
    }
}
