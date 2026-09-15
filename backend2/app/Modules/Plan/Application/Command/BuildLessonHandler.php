<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Command;

use App\Modules\Plan\Application\Dto\LessonRequest;
use App\Modules\Plan\Application\Dto\PlanConfig;
use App\Modules\Plan\Application\Exception\PlanModelUnavailable;
use App\Modules\Plan\Application\Port\LearnerGender;
use App\Modules\Plan\Application\Port\PlanDispatcher;
use App\Modules\Plan\Application\Service\LessonBuildService;
use App\Modules\Plan\Domain\Check\Language\LanguagePacks;
use App\Modules\Plan\Domain\Entity\PlanScene;
use App\Modules\Plan\Domain\Entity\PlanTerm;
use App\Modules\Plan\Domain\Repository\PlanRepository;
use App\Modules\Plan\Domain\Repository\PlanTermRepository;
use App\Modules\Plan\Domain\ValueObject\PlanTermId;
use App\Modules\Shared\Domain\Service\Clock;
use App\Modules\Shared\Domain\Service\LanguageName;
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
 * The learner's facts travel in TOPIC_DESCRIPTION: the scene's brief, then the plan's goal as the
 * learner wrote it — a fact that fits a frame's slot (years, field, family) becomes its filler. The
 * learner's gender is read from the profile now, at the moment the day is written.
 */
final readonly class BuildLessonHandler
{
    public function __construct(
        private PlanRepository $plans,
        private PlanTermRepository $terms,
        private LessonBuildService $builder,
        private PlanDispatcher $dispatcher,
        private PlanConfig $config,
        private LearnerGender $gender,
        private Clock $clock,
        private TransactionManager $tx,
        private LanguagePacks $packs,
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
        $counts = $this->config->countsFor($plan->level());

        $request = new LessonRequest(
            topic: $scene->titleNative(),
            topicDescription: self::topicDescription($scene->topicDescription(), $plan->goalText()),
            targetLanguage: LanguageName::of($plan->targetLang()->value),
            nativeLanguage: LanguageName::of($plan->nativeLang()->value),
            level: $plan->level(),
            learnerGender: $this->gender->of($plan->userId()),
            vocabularyCount: $counts['vocabulary'],
            dialogueCount: $counts['dialogue'],
            targetLangCode: $plan->targetLang()->value,
            nativeLangCode: $plan->nativeLang()->value,
        );

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
        $this->tx->run(function () use ($scene, $outcome, $now, $targetPack): void {
            if ($outcome->lesson === null || $outcome->call === null) {
                $scene->failLesson($outcome->failReason ?? 'unknown', $outcome->call, $outcome->findings);
                $this->plans->saveScene($scene);

                return;
            }
            $scene->acceptLesson($outcome->lesson, $targetPack, $outcome->call, $outcome->findings, $now);
            $this->plans->saveScene($scene);
            $served = $scene->lesson();
            if ($served !== null) {
                $this->terms->replaceForScene(
                    $scene->id(),
                    PlanTerm::fromLesson($scene->id(), $served, static fn (): PlanTermId => PlanTermId::generate()),
                );
            }
        });

        if ($scene->hasLesson()) {
            // The voice first: it may wait for the vendor's window, the photos never hold it up.
            $this->dispatcher->voiceScene($scene->id());
            $this->dispatcher->illustrateScene($scene->id());
        }
    }

    /** The scene's brief, then the learner's own words — the facts a frame's slot may take. */
    public static function topicDescription(string $brief, string $goal): string
    {
        $goal = trim((string) preg_replace('/\s+/u', ' ', $goal));

        return $goal === '' ? trim($brief) : trim($brief)."\n\nAbout the learner, in their own words: {$goal}";
    }
}
