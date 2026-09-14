<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Command;

use App\Modules\Plan\Application\Dto\PlanRequest;
use App\Modules\Plan\Application\Exception\PlanModelUnavailable;
use App\Modules\Plan\Application\Port\PlanDispatcher;
use App\Modules\Plan\Application\Service\PlanBuildService;
use App\Modules\Plan\Application\Service\PlanEventJournal;
use App\Modules\Plan\Application\Service\PlanNotifier;
use App\Modules\Plan\Domain\Blueprint\Blueprint;
use App\Modules\Plan\Domain\Entity\PlanEvent;
use App\Modules\Plan\Domain\Entity\Plan;
use App\Modules\Plan\Domain\Entity\PlanScene;
use App\Modules\Plan\Domain\Repository\PlanRepository;
use App\Modules\Plan\Domain\Service\PlanCalendar;
use App\Modules\Plan\Domain\ValueObject\DayType;
use App\Modules\Plan\Domain\ValueObject\ModelCall;
use App\Modules\Plan\Domain\ValueObject\PlanEventKind;
use App\Modules\Plan\Domain\ValueObject\PlanId;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;
use App\Modules\Plan\Domain\ValueObject\PlanStatus;
use App\Modules\Shared\Domain\Service\LanguageName;
use App\Modules\Shared\Domain\Service\TransactionManager;

/**
 * Runs the plan call. Idempotent: a plan that is not `building` (a fresh one, or one whose
 * extension has no empty scene days) is left alone, so a re-queued job cannot buy a second plan.
 * Day 1's lesson is queued the moment the plan is accepted — before «Начать» — and so are the
 * photos.
 *
 * The blueprint IS the aggregate (scenes, and the calendar they are laid onto), so there is no
 * pointwise column to write here. The plan is therefore re-read under `lockForUpdate` inside the
 * writing transaction and re-checked: the snapshot that went into the model call is a stale read
 * by the time it comes back, and a plan the learner deleted meanwhile must stay deleted.
 *
 * A plan that comes out `ready` gets its `plan_ready` journal line in the same transaction; the
 * letter it becomes is queued after the commit (PLAN-UI-3).
 */
final readonly class BuildPlanHandler
{
    public function __construct(
        private PlanRepository $plans,
        private PlanBuildService $builder,
        private PlanDispatcher $dispatcher,
        private TransactionManager $tx,
        private PlanEventJournal $journal,
        private PlanNotifier $notifier,
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
            $this->write($plan->id(), function (Plan $fresh) use ($e): void {
                $fresh->markFailed($e->getMessage(), null, []);
            });

            return;
        }

        /** @var PlanEvent|null $ready */
        $ready = null;
        $built = $this->write($plan->id(), function (Plan $fresh) use ($outcome, &$ready): void {
            if ($outcome->blueprint !== null && $outcome->call !== null) {
                $fresh->acceptBlueprint($outcome->blueprint, $outcome->call, $outcome->findings, static fn (): PlanSceneId => PlanSceneId::generate());
                $ready = $this->journal->record($fresh->id(), $fresh->userId(), PlanEventKind::PlanReady);
            } elseif ($outcome->unclearReason !== null && $outcome->call !== null) {
                $fresh->markUnclear($outcome->unclearReason, $outcome->call);
            } else {
                $fresh->markFailed($outcome->failReason ?? 'unknown', $outcome->call, $outcome->findings);
            }
        });

        if ($built !== null && $built->status() === PlanStatus::Ready) {
            // The route's pictures first: day 1's own job then finds its scene photographed already.
            $this->dispatcher->attachImages($built->id());
            $this->queueFirstLesson($built);
            $this->notifier->notify($ready);
        }
    }

    /**
     * The plan re-read under a lock, changed and saved in one transaction — the only way this job
     * writes. A plan that left `building` while the model was answering (deleted, or already
     * rebuilt by a retry) is left exactly as it is.
     *
     * @param  callable(Plan): void  $change
     */
    private function write(PlanId $planId, callable $change): ?Plan
    {
        return $this->tx->run(function () use ($planId, $change): ?Plan {
            $fresh = $this->plans->findByIdForUpdate($planId);
            if ($fresh === null || $fresh->status() !== PlanStatus::Building) {
                return null;
            }
            $change($fresh);
            $this->plans->save($fresh);

            return $fresh;
        });
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

        $extended = $this->tx->run(function () use ($plan, $outcome): ?Plan {
            $fresh = $this->plans->findByIdForUpdate($plan->id());
            if ($fresh === null || ! $fresh->status()->isBuilt()) {
                return null;
            }
            /** @var Blueprint $blueprint */
            $blueprint = $outcome->blueprint;
            /** @var ModelCall $call */
            $call = $outcome->call;
            $fresh->appendScenes($blueprint->scenes, $call, static fn (): PlanSceneId => PlanSceneId::generate());
            $this->plans->save($fresh);

            return $fresh;
        });
        if ($extended === null) {
            return;
        }

        $this->dispatcher->attachImages($extended->id());
        $this->queueFirstLesson($extended);
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
