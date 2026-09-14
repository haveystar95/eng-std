<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Command;

use App\Modules\Plan\Application\Port\PlanImageFinder;
use App\Modules\Plan\Application\Port\SceneImageStore;
use App\Modules\Plan\Application\Service\PlanImageLadder;
use App\Modules\Plan\Domain\Entity\PlanScene;
use App\Modules\Plan\Domain\Repository\PlanRepository;
use App\Modules\Plan\Domain\Repository\PlanTermRepository;
use App\Modules\Plan\Domain\ValueObject\Image;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;
use App\Modules\Plan\Domain\ValueObject\SceneImageSize;

/**
 * Same shape as the collection's photo job: only what lacks a photo is searched, and nothing here
 * can hold a day back.
 *
 * Every write is one photo into its own columns, conditional on the row still having none —
 * `UPDATE … WHERE image_url IS NULL`. The searches take seconds each and the scene the job read
 * at the start may have had its lesson written, the plan may have been started and day 1 opened by
 * the time they come back; a job that saved the aggregate it was holding undid all of it (11.09 on
 * the stand). Nothing here reads the plan's status and nothing here writes it.
 *
 * A scene, a word or a chunk without a photo is asked the whole ladder (DAY-UI-2,
 * `PlanImageLadder`): its description, then itself without context, then its theme. A word the
 * ladder found nothing for is painted with its scene's tone and not asked again by this job, which
 * runs after every lesson of the plan. Once a scene has its photo, the two square copies the client
 * shows are fetched and kept (PLAN-UI-3) — best effort: a copy that does not come is fetched by the
 * image endpoint on first request, and never fails this job.
 */
final readonly class AttachPlanImagesHandler
{
    public function __construct(
        private PlanRepository $plans,
        private PlanTermRepository $terms,
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

        /** @var array<string, string|null> $sceneTones */
        $sceneTones = [];
        foreach ($plan->scenes() as $scene) {
            $image = $scene->image() ?? $this->ladder->sceneImage($plan, $scene);
            if ($image !== null) {
                $this->keepCopies($scene->id(), $image);
            }
            $sceneTones[$scene->id()->value] = $image?->tone;
        }

        $ready = array_values(array_filter($plan->scenes(), static fn (PlanScene $s): bool => $s->isReady()));
        $readyIds = array_map(static fn (PlanScene $s): PlanSceneId => $s->id(), $ready);
        foreach ($this->terms->forScenes($readyIds) as $sceneId => $terms) {
            $scene = $plan->scene(PlanSceneId::fromString($sceneId));
            foreach ($terms as $term) {
                if ($term->needsImage()) {
                    $this->ladder->termImage($plan, $scene, $term, $sceneTones[$sceneId] ?? null);
                }
            }
        }
    }

    private function keepCopies(PlanSceneId $sceneId, Image $image): void
    {
        foreach (SceneImageSize::cases() as $size) {
            if (! $this->sceneImages->has($sceneId, $size)) {
                $this->sceneImages->fetch($sceneId, $image->url, $size);
            }
        }
    }
}
