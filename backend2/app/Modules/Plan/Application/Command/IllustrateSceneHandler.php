<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Command;

use App\Modules\Plan\Application\Port\SceneImageStore;
use App\Modules\Plan\Application\Port\SceneLocator;
use App\Modules\Plan\Application\Service\PlanImageLadder;
use App\Modules\Plan\Application\Service\SceneReadiness;
use App\Modules\Plan\Domain\Entity\PlanTerm;
use App\Modules\Plan\Domain\Repository\PlanRepository;
use App\Modules\Plan\Domain\Repository\PlanTermRepository;
use App\Modules\Plan\Domain\ValueObject\SceneImageSize;

/**
 * A DAY'S PICTURES, ALL AT ONCE, RIGHT AFTER ITS LESSON (DAY-UI-3).
 *
 * «Картинки ко всем словам и сцене дня запрашиваются в момент генерации дня, не при первом открытии.»
 * The scene's photo and every word's and chunk's climb their ladders together — one batch of the
 * finder per rung, six requests on the wire — and a word the whole ladder found nothing for gets its
 * tone. Then the scene is ready: the day opens with its pictures on it, its voice may still be on
 * the way (the phone's voice stands in).
 *
 * Idempotent: only what lacks a photo is searched, a photo is written only into an empty slot, and
 * the scene is made ready by a conditional write. A transient vendor error propagates — the job
 * retries — and when the job gives up, its failure path makes the day ready anyway: the photos are
 * best effort, the day is not.
 */
final readonly class IllustrateSceneHandler
{
    public function __construct(
        private SceneLocator $scenes,
        private PlanRepository $plans,
        private PlanTermRepository $terms,
        private PlanImageLadder $ladder,
        private SceneImageStore $sceneImages,
        private SceneReadiness $readiness,
    ) {}

    public function __invoke(IllustrateScene $command): void
    {
        $planId = $this->scenes->planIdOf($command->sceneId);
        $plan = $planId === null ? null : $this->plans->findById($planId);
        if ($plan === null) {
            return;
        }
        $scene = $plan->scene($command->sceneId);
        if (! $scene->hasLesson()) {
            return;
        }

        $photo = $this->ladder->scenePhotos($plan, [$scene])[$scene->id()->value] ?? null;
        if ($photo !== null) {
            // The two square copies the route and the compact header show — best effort: a copy that
            // does not come is fetched by the image endpoint on its first request.
            foreach (SceneImageSize::cases() as $size) {
                if (! $this->sceneImages->has($scene->id(), $size)) {
                    $this->sceneImages->fetch($scene->id(), $photo->url, $size);
                }
            }
        }

        $missing = array_values(array_filter(
            $this->terms->forScene($scene->id()),
            static fn (PlanTerm $t): bool => $t->needsImage(),
        ));
        if ($missing !== []) {
            $this->ladder->termPhotos($plan, $scene, $missing, $photo?->tone);
        }

        $this->readiness->markReady($plan->id(), $scene->id());
    }
}
