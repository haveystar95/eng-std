<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Command;

use App\Modules\Plan\Domain\Entity\PlanTerm;
use App\Modules\Plan\Domain\Exception\LessonAlreadyDealt;
use App\Modules\Plan\Domain\Exception\SceneNotFound;
use App\Modules\Plan\Domain\Repository\PlanRepository;
use App\Modules\Plan\Domain\Repository\PlanTermRepository;
use App\Modules\Plan\Domain\ValueObject\DayStatus;
use App\Modules\Plan\Domain\ValueObject\PlanTermId;
use App\Modules\Shared\Domain\Service\TransactionManager;

/**
 * Writes a repaired answer into its scene — only while the scene's day is not dealt yet ({@see
 * LessonAlreadyDealt}). The scene row is claimed like the lesson job claims it and is the only row of the
 * aggregate written; the terms keep their rows and photos, only what the lesson says in them is written
 * again from the served lesson.
 */
final readonly class ReviseLessonHandler
{
    public function __construct(
        private PlanRepository $plans,
        private PlanTermRepository $terms,
        private TransactionManager $tx,
    ) {}

    public function __invoke(ReviseLesson $command): void
    {
        $this->tx->run(function () use ($command): void {
            $scene = $this->plans->findSceneForUpdate($command->sceneId);
            $plan = $scene === null ? null : $this->plans->findById($scene->planId());
            if ($scene === null || $plan === null) {
                throw SceneNotFound::withId($command->sceneId);
            }
            foreach ($plan->days() as $day) {
                if ($day->sceneId()?->equals($scene->id()) && in_array($day->status(), [DayStatus::InProgress, DayStatus::Closed], true)) {
                    throw LessonAlreadyDealt::scene($scene->id(), $day->number());
                }
            }

            $scene->reviseLesson($command->answer, $command->findings, $command->repairCostUsd);
            $this->plans->saveScene($scene);
            $served = $scene->lesson();
            if ($served !== null) {
                $this->terms->rewriteTexts($scene->id(), PlanTerm::fromLesson($scene->id(), $served, static fn (): PlanTermId => PlanTermId::generate()));
            }
        });
    }
}
