<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Command;

use App\Modules\Plan\Application\Dto\PlanConfig;
use App\Modules\Plan\Application\Exception\PlanModelUnavailable;
use App\Modules\Plan\Application\Port\PlanDispatcher;
use App\Modules\Plan\Application\Service\LessonBuildService;
use App\Modules\Plan\Application\Service\LessonRequests;
use App\Modules\Plan\Application\Service\PartnerVoices;
use App\Modules\Plan\Domain\Check\Language\LanguagePacks;
use App\Modules\Plan\Domain\Entity\PlanScene;
use App\Modules\Plan\Domain\Entity\PlanTerm;
use App\Modules\Plan\Domain\Repository\PlanRepository;
use App\Modules\Plan\Domain\Repository\PlanTermRepository;
use App\Modules\Plan\Domain\ValueObject\PlanTermId;
use App\Modules\Shared\Domain\Service\Clock;
use App\Modules\Shared\Domain\Service\TransactionManager;

/**
 * Runs the lesson call for one scene. Idempotent: the scene is CLAIMED (`building`) inside a
 * transaction before the model is asked, so a second job for the same scene finds it claimed and
 * stops; a stale claim (a worker that died mid-call) is re-claimable after the configured window.
 * What is stored has passed the gate ({@see \App\Modules\Plan\Application\Service\LessonGateKeeper}): no
 * fatal finding is ever written as a lesson — its card was repaired, or the lesson failed with its code.
 * On success the terms are written from the served lesson and the scene waits for its photos
 * (`illustrating`): the photo job and the voice job are queued together and run side by side. The
 * photo job makes the day ready — and writes its `day_ready` line — when the pictures are in
 * ({@see IllustrateSceneHandler}); the voice never holds the day back (DAY-UI-3).
 *
 * THE SCENE ROW IS THE ONLY THING THIS JOB LOCKS AND THE ONLY THING IT WRITES. The model call
 * takes half a minute, and «Начать» is legal all the way through it: a job that came back holding
 * the aggregate it read before the call would write the plan's old status and the old calendar
 * over a plan the learner had already started (11.09 on the stand — the plan fell back to `ready`
 * with no start date and day 1 locked again). The plan is re-read after the claim, for the level,
 * the languages and the learner's own words the request needs, and never written.
 *
 * The inputs are put together by {@see LessonRequests}: the learner's facts in TOPIC_DESCRIPTION, the learner's gender
 * as the profile says it now, the roles of the plan and the scene, and the story so far — the days before this one whose
 * lessons are written (наряд GEN-3).
 *
 * THE LESSON ACCEPTED CASTS THE PARTNER'S VOICE (наряд FIX-4c §1): the role's gender is known only now, and the scene is
 * given the other voice of the nearest earlier scene of its gender ({@see PartnerVoices}). The plan's scene rows are
 * locked first, in the plan's order — the only place this job reads past its own row — so two lessons of one plan
 * accepted at once cannot both take the same voice.
 */
final readonly class BuildLessonHandler
{
    public function __construct(
        private PlanRepository $plans,
        private PlanTermRepository $terms,
        private LessonBuildService $builder,
        private PlanDispatcher $dispatcher,
        private PlanConfig $config,
        private LessonRequests $requests,
        private Clock $clock,
        private TransactionManager $tx,
        private LanguagePacks $packs,
        private PartnerVoices $voices,
    ) {}

    public function __invoke(BuildLesson $command): void
    {
        $now = $this->clock->now();

        $scene = $this->tx->run(function () use ($command, $now): ?PlanScene {
            $scene = $this->plans->findSceneForUpdate($command->sceneId);
            if ($scene === null) {
                return null;
            }
            if (! $scene->needsLesson() && ! $scene->isBuildStale($now, $this->config->buildStaleSeconds)) {
                return null;
            }
            $scene->startLessonBuild($now);
            $this->plans->saveScene($scene);

            return $scene;
        });
        if ($scene === null) {
            return;
        }

        $plan = $this->plans->findById($scene->planId());
        if ($plan === null) {
            return;
        }
        $request = $this->requests->for($plan, $scene);

        try {
            $outcome = $this->builder->build($request);
        } catch (PlanModelUnavailable $e) {
            $this->tx->run(function () use ($scene, $e): void {
                $scene->failLesson($e->getMessage(), null, []);
                $this->plans->saveScene($scene);
            });

            return;
        }

        $targetPack = $this->packs->for($plan->targetLang()->value);
        $nativePack = $this->packs->for($plan->nativeLang()->value);
        $this->tx->run(function () use ($plan, $scene, $outcome, $now, $targetPack, $nativePack): void {
            if ($outcome->lesson === null || $outcome->call === null) {
                $scene->failLesson($outcome->failReason ?? 'unknown', $outcome->call, $outcome->findings);
                $this->plans->saveScene($scene);

                return;
            }
            // Locked BEFORE this scene's row is written: every lesson job of the plan takes the rows in the same order.
            $voices = $this->plans->sceneVoicesForUpdate($scene->planId());
            $scene->acceptLesson($outcome->lesson, $targetPack, $outcome->call, $outcome->findings, $now);
            $this->voices->cast($scene, $plan->targetLang()->value, $voices);
            $this->plans->saveScene($scene);
            $served = $scene->lesson();
            if ($served !== null) {
                $this->terms->replaceForScene(
                    $scene->id(),
                    PlanTerm::fromLesson($scene->id(), $served, static fn (): PlanTermId => PlanTermId::generate(), $targetPack->sentenceEnds(), $nativePack->sentenceEnds()),
                );
            }
        });

        if ($scene->hasLesson()) {
            // The voice first: it may wait for the vendor's window, the photos never hold it up.
            $this->dispatcher->voiceScene($scene->id());
            $this->dispatcher->illustrateScene($scene->id());
        }
    }
}
