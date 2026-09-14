<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Command;

use App\Modules\Plan\Application\Port\SceneLocator;
use App\Modules\Plan\Application\Service\PlanImageLadder;
use App\Modules\Plan\Domain\Entity\Plan;
use App\Modules\Plan\Domain\Entity\PlanScene;
use App\Modules\Plan\Domain\Entity\PlanTerm;
use App\Modules\Plan\Domain\Repository\PlanRepository;
use App\Modules\Plan\Domain\Repository\PlanTermRepository;
use App\Modules\Plan\Domain\ValueObject\PlanId;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;
use App\Modules\Plan\Domain\ValueObject\TermKind;

/**
 * THE PHOTOS THE PLANS BUILT BEFORE STILL LACK (DAY-UI-2, the DAY-UI-3 ladder) — the first step of
 * `plan:images-backfill`.
 *
 * Every scene, word and chunk without a photo is asked the whole ladder again, the words the photo
 * job already painted with a tone too: a vendor that had nothing on one day may have it on another,
 * and a human running this command is asking exactly that. With `--requery` the words whose photo the
 * BARE WORD found (no description of their own — «marketing» → a supermarket) are asked the new
 * ladder, and a new answer replaces the old photo. A transient vendor error (a rate limit) propagates,
 * and the run is simply started again.
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
            $photos = $this->ladder->scenePhotos($plan, $plan->scenes());
            $written = array_map(
                static fn (PlanScene $s): PlanSceneId => $s->id(),
                array_values(array_filter($plan->scenes(), static fn (PlanScene $s): bool => $s->hasLesson())),
            );
            foreach ($this->terms->forScenes($written) as $sceneId => $terms) {
                $missing = array_values(array_filter(
                    $terms,
                    static fn (PlanTerm $t): bool => $t->kind() !== TermKind::Phrase && $t->image() === null,
                ));
                if ($missing !== []) {
                    $this->ladder->termPhotos($plan, $plan->scene(PlanSceneId::fromString($sceneId)), $missing, $photos[$sceneId]?->tone);
                }
            }
        }

        if ($command->requeryBareWords) {
            $this->requery($command->planId);
        }
    }

    private function requery(?PlanId $only): void
    {
        /** @var array<string, Plan|null> $plans */
        $plans = [];
        foreach ($this->terms->photographedWithoutPrompt($only) as $sceneId => $terms) {
            $id = PlanSceneId::fromString($sceneId);
            $planId = $this->scenes->planIdOf($id);
            if ($planId === null) {
                continue;
            }
            $plan = $plans[$planId->value] ??= $this->plans->findById($planId);
            if ($plan === null) {
                continue;
            }
            $scene = $plan->scene($id);
            $this->ladder->termPhotos($plan, $scene, $terms, $scene->image()?->tone, replace: true);
        }
    }
}
