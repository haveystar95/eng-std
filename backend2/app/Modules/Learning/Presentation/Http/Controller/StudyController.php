<?php

declare(strict_types=1);

namespace App\Modules\Learning\Presentation\Http\Controller;

use App\Modules\Learning\Application\Query\GetStudyCards;
use App\Modules\Learning\Application\Query\GetStudyCardsHandler;
use App\Modules\Learning\Application\Query\GetUserStats;
use App\Modules\Learning\Application\Query\GetUserStatsHandler;
use App\Modules\Learning\Presentation\Http\Resource\StatsResource;
use App\Modules\Learning\Presentation\Http\Resource\StudyCardResource;
use App\Modules\Shared\Domain\ValueObject\UserId;
use DateTimeImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Study session cards (due for review) and the training dashboard stats. */
final class StudyController
{
    public function __construct(
        private readonly GetStudyCardsHandler $studyCards,
        private readonly GetUserStatsHandler $userStats,
    ) {}

    public function due(Request $request): AnonymousResourceCollection
    {
        $cards = ($this->studyCards)(new GetStudyCards(
            userId: $this->actorId($request),
            now: new DateTimeImmutable(),
            sessionSize: $request->integer('limit', 20),
        ));

        return StudyCardResource::collection($cards);
    }

    public function stats(Request $request): StatsResource
    {
        $stats = ($this->userStats)(new GetUserStats($this->actorId($request), new DateTimeImmutable()));

        return new StatsResource($stats);
    }

    private function actorId(Request $request): UserId
    {
        return UserId::fromString((string) $request->user()?->getAuthIdentifier());
    }
}
