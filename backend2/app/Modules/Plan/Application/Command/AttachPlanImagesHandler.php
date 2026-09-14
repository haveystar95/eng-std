<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Command;

use App\Modules\Plan\Application\Port\PlanImageFinder;
use App\Modules\Plan\Application\Port\SceneImageStore;
use App\Modules\Plan\Application\Service\PlanImageLadder;
use App\Modules\Plan\Domain\Repository\PlanRepository;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;
use App\Modules\Plan\Domain\ValueObject\SceneImageSize;

/**
 * THE ROUTE'S PICTURES — the plan's cover and every scene's photo, as soon as the plan is built (or
 * extended), before any lesson is written. The words' photos are the day's and come with its lesson
 * ({@see IllustrateSceneHandler}, DAY-UI-3).
 *
 * Every write is one photo into its own columns, conditional on the row still having none —
 * `UPDATE … WHERE image_url IS NULL`: the searches take seconds and the plan may have been started
 * meanwhile; a job that saved the aggregate it was holding undid all of it (11.09 on the stand).
 * Nothing here reads the plan's status and nothing here writes it.
 *
 * The scenes climb their ladders together (`PlanImageLadder`, one batch per rung). Once a scene has
 * its photo, the two square copies the client shows are fetched and kept (PLAN-UI-3) — best effort.
 */
final readonly class AttachPlanImagesHandler
{
    public function __construct(
        private PlanRepository $plans,
        private PlanImageFinder $images,
        private SceneImageStore $sceneImages,
        private PlanImageLadder $ladder,
    ) {}

    public function __invoke(AttachPlanImages $command): void
    {
        $plan = $this->plans->findById($command->planId);
        if ($plan === null) {
            return;
        }

        $titles = $plan->titles();
        if ($plan->coverImage() === null && $titles !== null && trim($titles->coverImagePrompt) !== '') {
            $found = $this->images->find($titles->coverImagePrompt);
            if ($found !== null) {
                $this->plans->attachCoverImage($plan->id(), $found);
            }
        }

        foreach ($this->ladder->scenePhotos($plan, $plan->scenes()) as $sceneId => $image) {
            if ($image === null) {
                continue;
            }
            foreach (SceneImageSize::cases() as $size) {
                $id = PlanSceneId::fromString($sceneId);
                if (! $this->sceneImages->has($id, $size)) {
                    $this->sceneImages->fetch($id, $image->url, $size);
                }
            }
        }
    }
}
