<?php

declare(strict_types=1);

namespace App\Modules\Plan\Presentation\Http\Controller;

use App\Modules\Plan\Application\Command\StartConversation;
use App\Modules\Plan\Application\Command\StartConversationHandler;
use App\Modules\Plan\Application\Command\TakeConversationTurn;
use App\Modules\Plan\Application\Command\TakeConversationTurnHandler;
use App\Modules\Plan\Application\Dto\ConversationView;
use App\Modules\Plan\Application\Query\GetConversation;
use App\Modules\Plan\Application\Query\GetConversationHandler;
use App\Modules\Plan\Domain\ValueObject\ConversationId;
use App\Modules\Plan\Domain\ValueObject\PlanId;
use App\Modules\Plan\Presentation\Http\PlanJson;
use App\Modules\Plan\Presentation\Http\Request\ConversationTurnRequest;
use App\Modules\Plan\Presentation\Http\Request\StartConversationRequest;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * THE SIXTH STAGE OF A DAY (наряд CONV-1, кадры 37-5…37-12): start or carry on the talk, make one
 * move, or read the talk back after the connection dropped.
 *
 * All three answer with the SAME document — the whole ribbon, the state, the hint and, once it is
 * over, the summary. A client that lost a reply re-reads instead of guessing which half it missed.
 */
final class PlanConversationController
{
    public function __construct(
        private readonly StartConversationHandler $start,
        private readonly TakeConversationTurnHandler $turn,
        private readonly GetConversationHandler $read,
    ) {}

    /** «Начать разговор» · «Продолжить» · «Ещё раз» — one door, one open talk per day. */
    public function start(StartConversationRequest $request, string $id, int $number): JsonResponse
    {
        return self::reply(($this->start)(new StartConversation(
            planId: self::planId($id),
            number: $number,
            actorId: self::actorId($request),
            again: $request->again(),
            hints: $request->hints(),
        )));
    }

    public function show(Request $request, string $id, string $conversationId): JsonResponse
    {
        self::planId($id);

        return self::reply(($this->read)(new GetConversation(
            self::conversationId($conversationId), self::actorId($request),
        )));
    }

    public function move(ConversationTurnRequest $request, string $id, string $conversationId): JsonResponse
    {
        self::planId($id);

        return self::reply(($this->turn)(new TakeConversationTurn(
            conversationId: self::conversationId($conversationId),
            kind: $request->turnKind(),
            heard: $request->heard(),
            actorId: self::actorId($request),
        )));
    }

    private static function reply(ConversationView $view): JsonResponse
    {
        return response()->json(['data' => PlanJson::conversation($view)], 200, [], JSON_PRESERVE_ZERO_FRACTION);
    }

    private static function planId(string $id): PlanId
    {
        if (! Ulid::isValid($id)) {
            throw new NotFoundHttpException;
        }

        return PlanId::fromString($id);
    }

    private static function conversationId(string $id): ConversationId
    {
        if (! Ulid::isValid($id)) {
            throw new NotFoundHttpException;
        }

        return ConversationId::fromString($id);
    }

    private static function actorId(Request $request): UserId
    {
        return UserId::fromString((string) $request->user()?->getAuthIdentifier());
    }
}
