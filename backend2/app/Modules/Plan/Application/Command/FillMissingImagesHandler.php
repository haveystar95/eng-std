<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Command;

use App\Modules\Plan\Application\Port\SceneLocator;
use App\Modules\Plan\Application\Service\PlanImageLadder;
use App\Modules\Plan\Domain\Entity\PlanScene;
use App\Modules\Plan\Domain\Repository\PlanRepository;
use App\Modules\Plan\Domain\Repository\PlanTermRepository;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;
use App\Modules\Plan\Domain\ValueObject\TermKind;

/**
 * THE PHOTOS THE PLANS BUILT BEFORE THE LADDER STILL LACK (DAY-UI-2) — the first step of
 * `plan:images-backfill`.
 *
 * Every scene, word and chunk without a photo is asked the whole ladder again, the words the photo
 * job already painted with a tone too: a vendor that had nothing on one day may have it on another,
 * and a human running this command is asking exactly that. Idempotent in effect — a photo is only
 * ever written into an empty slot. A transient vendor error (a rate limit) propagates, and the run
 * is simply started again.
 */
final readonly class FillMissingImagesHandler
{
    public function __construct(
        private SceneLocator $scenes,
        private PlanRepository $plans,
        private PlanTermRepository $terms,
        private PlanImageLadder $ladder,
    ) {}

    public function __invoke(FillMissingImages $command): void
    {
        foreach ($this->scenes->plansMissingImages($command->planId) as $planId) {
            $plan = $this->plans->findById($planId);
            if ($plan === null) {
                continue;
            }

            /** @var array<string, string|null> $tones */
            $tones = [];
            foreach ($plan->scenes() as $scene) {
                $image = $scene->image() ?? $this->ladder->sceneImage($plan, $scene);
                $tones[$scene->id()->value] = $image?->tone;
            }

            $ready = array_map(
                static fn (PlanScene $s): PlanSceneId => $s->id(),
                array_values(array_filter($plan->scenes(), static fn (PlanScene $s): bool => $s->isReady())),
            );
            foreach ($this->terms->forScenes($ready) as $sceneId => $terms) {
                $scene = $plan->scene(PlanSceneId::fromString($sceneId));
                foreach ($terms as $term) {
                    if ($term->kind() !== TermKind::Phrase && $term->image() === null) {
                        $this->ladder->termImage($plan, $scene, $term, $tones[$sceneId] ?? null);
                    }
                }
            }
        }
    }
}
