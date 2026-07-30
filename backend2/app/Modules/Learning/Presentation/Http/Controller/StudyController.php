<?php

declare(strict_types=1);

namespace App\Modules\Learning\Presentation\Http\Controller;

use App\Modules\Learning\Application\Command\BuildStudySession;
use App\Modules\Learning\Application\Command\BuildStudySessionHandler;
use App\Modules\Learning\Application\Query\GetCollectionsProgress;
use App\Modules\Learning\Application\Query\GetCollectionsProgressHandler;
use App\Modules\Learning\Application\Query\GetUserStats;
use App\Modules\Learning\Application\Query\GetUserStatsHandler;
use App\Modules\Learning\Domain\ValueObject\StudySessionId;
use App\Modules\Learning\Presentation\Http\Request\BuildSessionRequest;
use App\Modules\Learning\Presentation\Http\Resource\CollectionProgressResource;
use App\Modules\Learning\Presentation\Http\Resource\SessionResource;
use App\Modules\Learning\Presentation\Http\Resource\StatsResource;
use App\Modules\Shared\Domain\ValueObject\UserId;
use DateTimeImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Study sessions (self-contained card packages), per-collection progress and dashboard stats. */
final class StudyController
{
    public function __construct(
        private readonly BuildStudySessionHandler $buildSession,
        private readonly GetUserStatsHandler $userStats,
        private readonly GetCollectionsProgressHandler $collectionsProgress,
    ) {}

    public function session(BuildSessionRequest $request): SessionResource
    {
        $collectionId = $request->string('collection_id')->toString();
        $sessionId = $request->string('session_id')->toString();

        $session = ($this->buildSession)(new BuildStudySession(
            actorId: $this->actorId($request),
            isPractice: $request->boolean('practice'),
            collectionId: $collectionId !== '' ? $collectionId : null,
            sessionSize: $request->integer('limit', 20),
            sessionId: $sessionId !== '' ? StudySessionId::fromString($sessionId) : null,
        ));

        return new SessionResource($session);
    }

    public function stats(Request $request): StatsResource
    {
        $stats = ($this->userStats)(new GetUserStats($this->actorId($request), new DateTimeImmutable()));

        return new StatsResource($stats);
    }

    public function progress(Request $request): AnonymousResourceCollection
    {
        $progress = ($this->collectionsProgress)(new GetCollectionsProgress($this->actorId($request), new DateTimeImmutable()));

        return CollectionProgressResource::collection($progress);
    }

    private function actorId(Request $request): UserId
    {
        return UserId::fromString((string) $request->user()?->getAuthIdentifier());
    }
}
