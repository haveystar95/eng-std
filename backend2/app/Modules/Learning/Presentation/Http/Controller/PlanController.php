<?php

declare(strict_types=1);

namespace App\Modules\Learning\Presentation\Http\Controller;

use App\Modules\Learning\Application\Command\BuildPlanOutline;
use App\Modules\Learning\Application\Command\BuildPlanOutlineHandler;
use App\Modules\Learning\Application\Command\BuildPlanSession;
use App\Modules\Learning\Application\Command\BuildPlanSessionHandler;
use App\Modules\Learning\Application\Command\CreatePlan;
use App\Modules\Learning\Application\Command\CreatePlanHandler;
use App\Modules\Learning\Application\Command\EndPlan;
use App\Modules\Learning\Application\Command\EndPlanHandler;
use App\Modules\Learning\Application\Command\RequestPlanDay;
use App\Modules\Learning\Application\Command\RequestPlanDayHandler;
use App\Modules\Learning\Application\Command\ReschedulePlan;
use App\Modules\Learning\Application\Command\ReschedulePlanHandler;
use App\Modules\Learning\Application\Command\StartPlan;
use App\Modules\Learning\Application\Command\StartPlanHandler;
use App\Modules\Learning\Application\Dto\PlanDayView;
use App\Modules\Learning\Application\Dto\PlanView;
use App\Modules\Learning\Application\Query\GetPlan;
use App\Modules\Learning\Application\Query\GetPlanHandler;
use App\Modules\Learning\Domain\ValueObject\PlanId;
use App\Modules\Learning\Domain\ValueObject\StudySessionId;
use App\Modules\Learning\Presentation\Http\Request\CreatePlanRequest;
use App\Modules\Learning\Presentation\Http\Request\ReschedulePlanRequest;
use App\Modules\Learning\Presentation\Http\Resource\PlanResource;
use App\Modules\Learning\Presentation\Http\Resource\PlanSessionResource;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * THE PLAN — «цель + дата + минуты» in, a dated mechanism out.
 *
 * The three-step shape of the create flow is deliberate and is what makes the feature honest:
 *
 *   POST /plans                    a DRAFT. Free — no model call, no day, no word enrolled.
 *   POST /plans/{id}/outline       P1 + A1. The skeleton the learner READS before committing.
 *   PATCH /plans/{id}/outline      A1 again — fewer minutes, a different date, one day fewer.
 *                                  No model call, so adjusting is free and can be done twice.
 *   POST /plans/{id}/start         the commitment: days start generating, words start being held.
 *
 * The learner sees what they are buying before they buy it, and the adjustment step costs nothing —
 * which is the whole reason `outline` and `computed` are two columns and A1 is a pure function.
 *
 * There is no DELETE. A plan is ended, not deleted: `pause` keeps its hold on the pool, `abandon`
 * releases it, and both leave the record of what was promised. A deleted plan would take its own
 * explanation of why three hundred words are in the learner's pool with it.
 */
final class PlanController
{
    public function __construct(
        private readonly CreatePlanHandler $create,
        private readonly BuildPlanOutlineHandler $outline,
        private readonly ReschedulePlanHandler $reschedule,
        private readonly StartPlanHandler $start,
        private readonly EndPlanHandler $end,
        private readonly GetPlanHandler $get,
        private readonly BuildPlanSessionHandler $buildSession,
        private readonly RequestPlanDayHandler $requestDay,
    ) {}

    public function store(CreatePlanRequest $request): JsonResponse
    {
        $planId = ($this->create)(new CreatePlan(
            actorId: $this->actorId($request),
            goalText: (string) $request->input('goal_text'),
            targetLang: (string) $request->input('target_lang'),
            level: (string) $request->input('level'),
            eventDate: (string) $request->input('event_date'),
            minutesPerDay: (int) $request->input('minutes_per_day', 20),
        ));

        return $this->show($request, $planId->value, Response::HTTP_CREATED);
    }

    /** P1 + A1. Re-running it REBUILDS the skeleton, which is why it is a POST and not a PUT. */
    public function buildOutline(Request $request, string $planId): JsonResponse
    {
        ($this->outline)(new BuildPlanOutline($this->planId($planId), $this->actorId($request)));

        return $this->show($request, $planId);
    }

    public function reschedule(ReschedulePlanRequest $request, string $planId): JsonResponse
    {
        ($this->reschedule)(new ReschedulePlan(
            planId: $this->planId($planId),
            actorId: $this->actorId($request),
            minutesPerDay: $request->has('minutes_per_day') ? (int) $request->input('minutes_per_day') : null,
            eventDate: $request->has('event_date') ? (string) $request->input('event_date') : null,
            dropDayIndex: $request->has('drop_day_index') ? (int) $request->input('drop_day_index') : null,
        ));

        return $this->show($request, $planId);
    }

    public function start(Request $request, string $planId): JsonResponse
    {
        ($this->start)(new StartPlan($this->planId($planId), $this->actorId($request)));

        return $this->show($request, $planId);
    }

    public function pause(Request $request, string $planId): JsonResponse
    {
        return $this->finish($request, $planId, EndPlan::PAUSE);
    }

    public function abandon(Request $request, string $planId): JsonResponse
    {
        return $this->finish($request, $planId, EndPlan::ABANDON);
    }

    /** The plan the learner is currently on, or 204 when there is none. */
    public function active(Request $request): JsonResponse
    {
        $plan = ($this->get)(new GetPlan($this->actorId($request)));

        return $plan === null
            ? new JsonResponse(null, Response::HTTP_NO_CONTENT)
            : new JsonResponse(['data' => PlanResource::toArray($plan)]);
    }

    public function show(Request $request, string $planId, int $status = Response::HTTP_OK): JsonResponse
    {
        return new JsonResponse(['data' => PlanResource::toArray($this->plan($request, $planId))], $status);
    }

    /**
     * The session of ONE day — the plan actually being studied.
     *
     * `dayIndex` is optional: without one the server deals the day the learner is on, which is the
     * only day the client can be sure about without recomputing the focus itself. With one, the
     * named day — strict if it IS the focus, an ordinary soft run over its material if it is not.
     */
    public function session(Request $request, string $planId, ?string $dayIndex = null): JsonResponse
    {
        $session = ($this->buildSession)(new BuildPlanSession(
            actorId: $this->actorId($request),
            planId: $this->planId($planId)->value,
            dayIndex: $dayIndex !== null ? (int) $dayIndex : null,
            sessionId: $request->string('session_id')->toString() !== ''
                ? StudySessionId::fromString($request->string('session_id')->toString())
                : null,
        ));

        return new JsonResponse(['data' => (new PlanSessionResource($session))->toArray($request)]);
    }

    /**
     * «Собери мне день n» — the learner looking ahead of the focus.
     *
     * Idempotent and cheap to poll: it answers with the day's STATUS, so the «собираю день n» screen
     * can call it to start the work and call it again to find out whether it finished.
     */
    public function generateDay(Request $request, string $planId, string $dayIndex): JsonResponse
    {
        $status = ($this->requestDay)(new RequestPlanDay(
            actorId: $this->actorId($request),
            planId: $this->planId($planId)->value,
            dayIndex: (int) $dayIndex,
        ));

        return new JsonResponse(['data' => ['day_index' => (int) $dayIndex, 'status' => $status]]);
    }

    public function day(Request $request, string $planId, string $dayIndex): JsonResponse
    {
        $plan = $this->plan($request, $planId);

        foreach ($plan->days as $day) {
            if ($day->index === (int) $dayIndex) {
                return new JsonResponse(['data' => $this->dayBody($plan, $day)]);
            }
        }

        throw new NotFoundHttpException();
    }

    private function finish(Request $request, string $planId, string $action): JsonResponse
    {
        ($this->end)(new EndPlan($this->planId($planId), $this->actorId($request), $action));

        return $this->show($request, $planId);
    }

    /** @return array<string, mixed> */
    private function dayBody(PlanView $plan, PlanDayView $day): array
    {
        // The day carries its plan's binding lists with it: the client reading one day needs the
        // entities and the goal terms to render it, and a second request for the plan to get them
        // would be a round trip for data the server already had in hand.
        return [
            ...PlanResource::day($day),
            'plan_id' => $plan->id,
            'plan_title' => $plan->title,
            'support_lang' => $plan->supportLang,
            'target_lang' => $plan->targetLang,
            'level' => $plan->level,
            'entities' => $plan->entities,
            'constraints' => $plan->constraints,
            'goal_terms' => $plan->goalTerms,
        ];
    }

    private function plan(Request $request, string $planId): PlanView
    {
        $plan = ($this->get)(new GetPlan($this->actorId($request), $this->planId($planId)->value));

        // A plan of another learner is a 404, not a 403: whether a given ULID exists is not
        // something a stranger gets to find out.
        return $plan ?? throw new NotFoundHttpException();
    }

    private function planId(string $planId): PlanId
    {
        try {
            return PlanId::fromString($planId);
        } catch (InvalidArgumentException $e) {
            throw new NotFoundHttpException(previous: $e);
        }
    }

    private function actorId(Request $request): UserId
    {
        return UserId::fromString((string) $request->user()?->getAuthIdentifier());
    }
}
