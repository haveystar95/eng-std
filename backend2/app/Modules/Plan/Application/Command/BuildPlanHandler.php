<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Command;

use App\Modules\Plan\Application\Dto\PlanRequest;
use App\Modules\Plan\Application\Exception\PlanModelUnavailable;
use App\Modules\Plan\Application\Port\PlanDispatcher;
use App\Modules\Plan\Application\Service\PlanBuildService;
use App\Modules\Plan\Domain\Blueprint\Blueprint;
use App\Modules\Plan\Domain\Entity\Plan;
use App\Modules\Plan\Domain\Entity\PlanScene;
use App\Modules\Plan\Domain\Repository\PlanRepository;
use App\Modules\Plan\Domain\Service\PlanCalendar;
use App\Modules\Plan\Domain\ValueObject\DayType;
use App\Modules\Plan\Domain\ValueObject\ModelCall;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;
use App\Modules\Plan\Domain\ValueObject\PlanStatus;
use App\Modules\Shared\Domain\Service\LanguageName;
use App\Modules\Shared\Domain\Service\TransactionManager;

/**
 * Runs the plan call. Idempotent: a plan that is not `building` (a fresh one, or one whose
 * extension has no empty scene days) is left alone, so a re-queued job cannot buy a second plan.
 * Day 1's lesson is queued the moment the plan is accepted — before «Начать» — and so are the
 * photos.
 */
final readonly class BuildPlanHandler
{
    public function __construct(
        private PlanRepository $plans,
        private PlanBuildService $builder,
        private PlanDispatcher $dispatcher,
        private TransactionManager $tx,
    ) {}

    public function __invoke(BuildPlan $command): void
    {
        $plan = $this->plans->findById($command->planId);
        if ($plan === null) {
            return;
        }

        if ($command->scenesToAdd > 0) {
            $this->extend($plan, $command->scenesToAdd);

            return;
        }

        if ($plan->status() !== PlanStatus::Building) {
            return;
        }

        $request = $this->request($plan, PlanCalendar::scenesCount($plan->daysTotal()), []);

        try {
            $outcome = $this->builder->build($request);
        } catch (PlanModelUnavailable $e) {
            $this->tx->run(function () use ($plan, $e): void {
                $plan->markFailed($e->getMessage(), null, []);
                $this->plans->save($plan);
            });

            return;
        }

        $this->tx->run(function () use ($plan, $outcome): void {
            if ($outcome->blueprint !== null && $outcome->call !== null) {
                $plan->acceptBlueprint($outcome->blueprint, $outcome->call, $outcome->findings, static fn (): PlanSceneId => PlanSceneId::generate());
            } elseif ($outcome->unclearReason !== null && $outcome->call !== null) {
                $plan->markUnclear($outcome->unclearReason, $outcome->call);
            } else {
                $plan->markFailed($outcome->failReason ?? 'unknown', $outcome->call, $outcome->findings);
            }
            $this->plans->save($plan);
        });

        if ($plan->status() === PlanStatus::Ready) {
            $this->queueFirstLesson($plan);
            $this->dispatcher->attachImages($plan->id());
        }
    }

    private function extend(Plan $plan, int $scenesToAdd): void
    {
        $existing = array_map(static fn (PlanScene $s): string => $s->titleNative(), $plan->scenes());
        $request = $this->request($plan, $scenesToAdd, $existing);

        try {
            $outcome = $this->builder->build($request);
        } catch (PlanModelUnavailable) {
            return; // the empty scene days stay empty; a later reschedule asks again
        }
        if ($outcome->blueprint === null || $outcome->call === null) {
            return;
        }

        $this->tx->run(function () use ($plan, $outcome): void {
            /** @var Blueprint $blueprint */
            $blueprint = $outcome->blueprint;
            /** @var ModelCall $call */
            $call = $outcome->call;
            $plan->appendScenes($blueprint->scenes, $call, static fn (): PlanSceneId => PlanSceneId::generate());
            $this->plans->save($plan);
        });

        $this->dispatcher->attachImages($plan->id());
        $this->queueFirstLesson($plan);
    }

    /** Day 1's lesson is written before «Начать»; after a start the next open scene day is what waits. */
    private function queueFirstLesson(Plan $plan): void
    {
        $current = $plan->currentDay();
        if ($current === null) {
            return;
        }
        $day = $current->sceneId() !== null && $current->type() === DayType::Scene
            ? $current
            : $plan->nextSceneDayAfter($current->number());
        $scene = $day === null ? null : $plan->sceneOf($day);
        if ($scene !== null && $scene->needsLesson()) {
            $this->dispatcher->buildLesson($scene->id());
        }
    }

    /** @param list<string> $existing */
    private function request(Plan $plan, int $scenesCount, array $existing): PlanRequest
    {
        return new PlanRequest(
            goal: $plan->goalText(),
            targetLanguage: LanguageName::of($plan->targetLang()->value),
            nativeLanguage: LanguageName::of($plan->nativeLang()->value),
            level: $plan->level(),
            scenesCount: $scenesCount,
            existingScenes: $existing,
        );
    }
}
