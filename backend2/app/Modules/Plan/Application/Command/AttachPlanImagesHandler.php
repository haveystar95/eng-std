<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Command;

use App\Modules\Plan\Application\Port\PlanImageFinder;
use App\Modules\Plan\Domain\Entity\PlanScene;
use App\Modules\Plan\Domain\Repository\PlanRepository;
use App\Modules\Plan\Domain\Repository\PlanTermRepository;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;

/**
 * Same shape as the collection's photo job: only what lacks a photo is searched, an empty result
 * is a null and not a retry, and nothing here can hold a day back.
 */
final readonly class AttachPlanImagesHandler
{
    public function __construct(
        private PlanRepository $plans,
        private PlanTermRepository $terms,
        private PlanImageFinder $images,
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
                $plan->attachCoverImage($found);
            }
        }
        foreach ($plan->scenes() as $scene) {
            if ($scene->image() === null && trim($scene->imagePrompt()) !== '') {
                $found = $this->images->find($scene->imagePrompt());
                if ($found !== null) {
                    $scene->attachImage($found);
                }
            }
        }
        $this->plans->save($plan);

        $ready = array_map(static fn (PlanScene $s): PlanSceneId => $s->id(), array_values(array_filter(
            $plan->scenes(), static fn (PlanScene $s): bool => $s->isReady(),
        )));
        foreach ($this->terms->forScenes($ready) as $terms) {
            foreach ($terms as $term) {
                if (! $term->needsImage()) {
                    continue;
                }
                $found = $this->images->find((string) $term->imagePrompt());
                if ($found !== null) {
                    $term->attachImage($found);
                    $this->terms->save($term);
                }
            }
        }
    }
}
