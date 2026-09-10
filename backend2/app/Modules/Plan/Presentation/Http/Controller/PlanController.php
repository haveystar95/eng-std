<?php

declare(strict_types=1);

namespace App\Modules\Plan\Presentation\Http\Controller;

use App\Modules\Plan\Application\Command\CreatePlan;
use App\Modules\Plan\Application\Command\CreatePlanHandler;
use App\Modules\Plan\Application\Command\DeletePlan;
use App\Modules\Plan\Application\Command\DeletePlanHandler;
use App\Modules\Plan\Application\Command\FinishPlan;
use App\Modules\Plan\Application\Command\FinishPlanHandler;
use App\Modules\Plan\Application\Command\RemoveScene;
use App\Modules\Plan\Application\Command\RemoveSceneHandler;
use App\Modules\Plan\Application\Command\ReschedulePlan;
use App\Modules\Plan\Application\Command\ReschedulePlanHandler;
use App\Modules\Plan\Application\Command\RetryLesson;
use App\Modules\Plan\Application\Command\RetryLessonHandler;
use App\Modules\Plan\Application\Command\RetryPlanBuild;
use App\Modules\Plan\Application\Command\RetryPlanBuildHandler;
use App\Modules\Plan\Application\Command\StartPlan;
use App\Modules\Plan\Application\Command\StartPlanHandler;
use App\Modules\Plan\Application\Query\GetCurrentPlan;
use App\Modules\Plan\Application\Query\GetCurrentPlanHandler;
use App\Modules\Plan\Application\Query\GetPlan;
use App\Modules\Plan\Application\Query\GetPlanBuild;
use App\Modules\Plan\Application\Query\GetPlanBuildHandler;
use App\Modules\Plan\Application\Query\GetPlanHandler;
use App\Modules\Plan\Application\Query\GetVersions;
use App\Modules\Plan\Application\Query\GetVersionsHandler;
use App\Modules\Plan\Application\Query\ListPlans;
use App\Modules\Plan\Application\Query\ListPlansHandler;
use App\Modules\Plan\Domain\ValueObject\PlanId;
use App\Modules\Plan\Domain\ValueObject\PlanLevel;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;
use App\Modules\Plan\Presentation\Http\PlanJson;
use App\Modules\Plan\Presentation\Http\Request\CreatePlanRequest;
use App\Modules\Plan\Presentation\Http\Request\ReschedulePlanRequest;
use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use App\Modules\Shared\Domain\ValueObject\UserId;
use DateTimeImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** The plan: create and poll, preview, start, tab, menu (`docs/plan-api.md`). Translation only. */
final class PlanController
{
    public function __construct(
        private readonly ListPlansHandler $list,
        private readonly CreatePlanHandler $create,
        private readonly GetCurrentPlanHandler $current,
        private readonly GetPlanHandler $get,
        private readonly GetPlanBuildHandler $build,
        private readonly RetryPlanBuildHandler $retryBuild,
        private readonly RemoveSceneHandler $removeScene,
        private readonly RetryLessonHandler $retryLesson,
        private readonly StartPlanHandler $start,
        private readonly ReschedulePlanHandler $reschedule,
        private readonly FinishPlanHandler $finish,
        private readonly DeletePlanHandler $delete,
        private readonly GetVersionsHandler $versions,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $plans = ($this->list)(new ListPlans($this->actorId($request)));

        return response()->json(['data' => array_map(PlanJson::summary(...), $plans)]);
    }

    /** 202: the row exists, the model is being asked; poll `GET /plans/{id}/build`. */
    public function store(CreatePlanRequest $request): JsonResponse
    {
        $data = $request->validated();
        $actor = $this->actorId($request);
        $eventDate = isset($data['event_date']) && is_string($data['event_date']) ? new DateTimeImmutable($data['event_date']) : null;

        $id = ($this->create)(new CreatePlan(
            actorId: $actor,
            goalText: (string) $data['goal_text'],
            targetLang: new LanguageCode((string) $data['target_lang']),
            level: PlanLevel::from((string) $data['level']),
            daysTotal: (int) $data['days_total'],
            eventDate: $eventDate,
        ));

        return response()->json(['data' => PlanJson::build(($this->build)(new GetPlanBuild($id, $actor)))], Response::HTTP_ACCEPTED);
    }

    public function current(Request $request): JsonResponse
    {
        $view = ($this->current)(new GetCurrentPlan($this->actorId($request)));

        return response()->json(['data' => $view === null ? null : PlanJson::plan($view)]);
    }

    public function versions(): JsonResponse
    {
        return response()->json(['data' => PlanJson::versions(($this->versions)(new GetVersions))]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        return response()->json(['data' => PlanJson::plan(($this->get)(new GetPlan($this->planId($id), $this->actorId($request))))]);
    }

    public function buildStatus(Request $request, string $id): JsonResponse
    {
        return response()->json(['data' => PlanJson::build(($this->build)(new GetPlanBuild($this->planId($id), $this->actorId($request))))]);
    }

    public function retryBuild(Request $request, string $id): JsonResponse
    {
        $actor = $this->actorId($request);
        $planId = $this->planId($id);
        ($this->retryBuild)(new RetryPlanBuild($planId, $actor));

        return response()->json(['data' => PlanJson::build(($this->build)(new GetPlanBuild($planId, $actor)))], Response::HTTP_ACCEPTED);
    }

    public function removeScene(Request $request, string $id, string $sceneId): JsonResponse
    {
        $actor = $this->actorId($request);
        $planId = $this->planId($id);
        ($this->removeScene)(new RemoveScene($planId, $this->sceneId($sceneId), $actor));

        return $this->plan($planId, $actor);
    }

    public function retryLesson(Request $request, string $id, string $sceneId): JsonResponse
    {
        $actor = $this->actorId($request);
        $planId = $this->planId($id);
        ($this->retryLesson)(new RetryLesson($planId, $this->sceneId($sceneId), $actor));

        return $this->plan($planId, $actor, Response::HTTP_ACCEPTED);
    }

    public function start(Request $request, string $id): JsonResponse
    {
        $actor = $this->actorId($request);
        $planId = $this->planId($id);
        ($this->start)(new StartPlan($planId, $actor));

        return $this->plan($planId, $actor);
    }

    public function reschedule(ReschedulePlanRequest $request, string $id): JsonResponse
    {
        $data = $request->validated();
        $actor = $this->actorId($request);
        $planId = $this->planId($id);
        $hasDate = array_key_exists('event_date', $data);

        ($this->reschedule)(new ReschedulePlan(
            planId: $planId,
            actorId: $actor,
            eventDate: $hasDate && is_string($data['event_date']) ? new DateTimeImmutable($data['event_date']) : null,
            clearEventDate: $hasDate && $data['event_date'] === null,
            daysTotal: isset($data['days_total']) ? (int) $data['days_total'] : null,
        ));

        return $this->plan($planId, $actor);
    }

    public function finish(Request $request, string $id): JsonResponse
    {
        $actor = $this->actorId($request);
        $planId = $this->planId($id);
        ($this->finish)(new FinishPlan($planId, $actor));

        return $this->plan($planId, $actor);
    }

    public function destroy(Request $request, string $id): Response
    {
        ($this->delete)(new DeletePlan($this->planId($id), $this->actorId($request)));

        return response()->noContent();
    }

    private function plan(PlanId $planId, UserId $actor, int $status = Response::HTTP_OK): JsonResponse
    {
        return response()->json(['data' => PlanJson::plan(($this->get)(new GetPlan($planId, $actor)))], $status);
    }

    private function planId(string $id): PlanId
    {
        if (! Ulid::isValid($id)) {
            throw new NotFoundHttpException;
        }

        return PlanId::fromString($id);
    }

    private function sceneId(string $id): PlanSceneId
    {
        if (! Ulid::isValid($id)) {
            throw new NotFoundHttpException;
        }

        return PlanSceneId::fromString($id);
    }

    private function actorId(Request $request): UserId
    {
        return UserId::fromString((string) $request->user()?->getAuthIdentifier());
    }
}
