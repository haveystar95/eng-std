<?php

declare(strict_types=1);

namespace App\Modules\Plan\Presentation\Http\Controller;

use App\Modules\Plan\Application\Command\JudgeCard;
use App\Modules\Plan\Application\Command\JudgeCardHandler;
use App\Modules\Plan\Application\Service\CardViews;
use App\Modules\Plan\Domain\ValueObject\DayCardId;
use App\Modules\Plan\Domain\ValueObject\PlanId;
use App\Modules\Plan\Presentation\Http\PlanJson;
use App\Modules\Plan\Presentation\Http\Request\JudgeCardRequest;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** `POST /plans/{id}/days/{number}/cards/{cardId}/judge` — the slot judge's verdict on one attempt (наряд SESSION-1a, разд. 4). */
final class PlanCardJudgeController
{
    public function __construct(
        private readonly JudgeCardHandler $handler,
        private readonly CardViews $cardViews,
    ) {}

    public function judge(JudgeCardRequest $request, string $id, int $number, string $cardId): JsonResponse
    {
        if (! Ulid::isValid($id) || ! Ulid::isValid($cardId)) {
            throw new NotFoundHttpException;
        }
        $data = $request->validated();

        $outcome = ($this->handler)(new JudgeCard(
            planId: PlanId::fromString($id),
            number: $number,
            cardId: DayCardId::fromString($cardId),
            // Silence is an attempt too: an empty `heard` arrives as null (ConvertEmptyStringsToNull).
            heard: is_string($data['heard'] ?? null) ? $data['heard'] : '',
            hinted: (bool) $data['hinted'],
            actorId: UserId::fromString((string) $request->user()?->getAuthIdentifier()),
        ));

        $view = $this->cardViews->forCards([$outcome->card], $outcome->targetLang, $outcome->dayNumbers)[0];
        $verdict = $outcome->verdict;

        // The card goes out with its shares as shares (`coverage_min` 1.0), like every reply carrying cards.
        return response()->json(['data' => PlanJson::judge($verdict->accepted, $verdict->slotValue, $verdict->reasonNative, $view, $outcome->heard)], 200, [], JSON_PRESERVE_ZERO_FRACTION);
    }
}
