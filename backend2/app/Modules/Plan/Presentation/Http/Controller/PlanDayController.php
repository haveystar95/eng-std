<?php

declare(strict_types=1);

namespace App\Modules\Plan\Presentation\Http\Controller;

use App\Modules\Plan\Application\Command\AnswerCard;
use App\Modules\Plan\Application\Command\AnswerCardHandler;
use App\Modules\Plan\Application\Command\CloseDay;
use App\Modules\Plan\Application\Command\CloseDayHandler;
use App\Modules\Plan\Application\Command\CloseStage;
use App\Modules\Plan\Application\Command\CloseStageHandler;
use App\Modules\Plan\Application\Command\OpenDay;
use App\Modules\Plan\Application\Command\OpenDayHandler;
use App\Modules\Plan\Application\Query\GetDayCards;
use App\Modules\Plan\Application\Query\GetDayCardsHandler;
use App\Modules\Plan\Application\Query\GetDayRoom;
use App\Modules\Plan\Application\Query\GetDayRoomHandler;
use App\Modules\Plan\Application\Query\GetPlanTargetLang;
use App\Modules\Plan\Application\Service\CardViews;
use App\Modules\Plan\Domain\ValueObject\CardResult;
use App\Modules\Plan\Domain\ValueObject\DayCardId;
use App\Modules\Plan\Domain\ValueObject\PlanId;
use App\Modules\Plan\Domain\ValueObject\Stage;
use App\Modules\Plan\Presentation\Http\PlanJson;
use App\Modules\Plan\Presentation\Http\Request\AnswerCardRequest;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** One day of the plan: the room, the cards, the answers, the closes. */
final class PlanDayController
{
    public function __construct(
        private readonly GetDayRoomHandler $room,
        private readonly OpenDayHandler $open,
        private readonly GetDayCardsHandler $cards,
        private readonly AnswerCardHandler $answer,
        private readonly CloseStageHandler $closeStage,
        private readonly CloseDayHandler $closeDay,
        private readonly CardViews $cardViews,
        private readonly GetPlanTargetLang $targetLang,
    ) {}

    public function room(Request $request, string $id, int $number): JsonResponse
    {
        return response()->json(['data' => PlanJson::room(($this->room)(new GetDayRoom($this->planId($id), $number, $this->actorId($request))))]);
    }

    /** «Открыть» / «Продолжить»: deals the cards on the first call, returns the full list every time. */
    public function open(Request $request, string $id, int $number): JsonResponse
    {
        $actor = $this->actorId($request);
        $planId = $this->planId($id);
        ($this->open)(new OpenDay($planId, $number, $actor));

        return $this->cardList($planId, $number, $actor);
    }

    public function cards(Request $request, string $id, int $number): JsonResponse
    {
        return $this->cardList($this->planId($id), $number, $this->actorId($request));
    }

    public function answer(AnswerCardRequest $request, string $id, int $number, string $cardId): JsonResponse
    {
        $data = $request->validated();
        $actor = $this->actorId($request);
        $planId = $this->planId($id);
        if (! Ulid::isValid($cardId)) {
            throw new NotFoundHttpException;
        }

        $outcome = ($this->answer)(new AnswerCard(
            planId: $planId,
            number: $number,
            cardId: DayCardId::fromString($cardId),
            result: CardResult::from((string) $data['result']),
            attempts: (int) $data['attempts'],
            actorId: $actor,
        ));

        $lang = ($this->targetLang)($planId, $actor);
        $views = $this->cardViews->forCards(array_filter([$outcome->card, $outcome->requeued]), $lang);

        return response()->json(['data' => [
            'card' => PlanJson::card($views[0]),
            'requeued' => isset($views[1]) ? PlanJson::card($views[1]) : null,
        ]]);
    }

    public function closeStage(Request $request, string $id, int $number, string $stage): JsonResponse
    {
        $parsed = Stage::tryFrom($stage);
        if ($parsed === null) {
            throw new NotFoundHttpException;
        }
        $actor = $this->actorId($request);
        $planId = $this->planId($id);
        ($this->closeStage)(new CloseStage($planId, $number, $parsed, $actor));

        return response()->json(['data' => PlanJson::room(($this->room)(new GetDayRoom($planId, $number, $actor)))]);
    }

    public function close(Request $request, string $id, int $number): JsonResponse
    {
        $actor = $this->actorId($request);
        $planId = $this->planId($id);
        ($this->closeDay)(new CloseDay($planId, $number, $actor));

        return response()->json(['data' => PlanJson::room(($this->room)(new GetDayRoom($planId, $number, $actor)))]);
    }

    private function cardList(PlanId $planId, int $number, UserId $actor): JsonResponse
    {
        $view = ($this->cards)(new GetDayCards($planId, $number, $actor));

        return response()->json(['data' => [
            'plan_id' => $view->planId,
            'day_id' => $view->dayId,
            'number' => $view->number,
            'status' => $view->status,
            'cards' => array_map(PlanJson::card(...), $view->cards),
        ]]);
    }

    private function planId(string $id): PlanId
    {
        if (! Ulid::isValid($id)) {
            throw new NotFoundHttpException;
        }

        return PlanId::fromString($id);
    }

    private function actorId(Request $request): UserId
    {
        return UserId::fromString((string) $request->user()?->getAuthIdentifier());
    }
}
