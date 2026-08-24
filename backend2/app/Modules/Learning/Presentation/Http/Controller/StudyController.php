<?php

declare(strict_types=1);

namespace App\Modules\Learning\Presentation\Http\Controller;

use App\Modules\Learning\Application\Command\BuildStudySession;
use App\Modules\Learning\Application\Command\BuildStudySessionHandler;
use App\Modules\Learning\Application\Command\CompleteStudySession;
use App\Modules\Learning\Application\Command\CompleteStudySessionHandler;
use App\Modules\Learning\Application\Query\GetCollectionsProgress;
use App\Modules\Learning\Application\Query\GetCollectionsProgressHandler;
use App\Modules\Learning\Application\Query\GetProgressByLanguage;
use App\Modules\Learning\Application\Query\GetProgressByLanguageHandler;
use App\Modules\Learning\Application\Query\GetUserStats;
use App\Modules\Learning\Application\Query\GetUserStatsHandler;
use App\Modules\Learning\Domain\ValueObject\StudySessionId;
use App\Modules\Learning\Presentation\Http\Request\BuildSessionRequest;
use App\Modules\Learning\Presentation\Http\Request\CompleteSessionRequest;
use App\Modules\Learning\Presentation\Http\Resource\CollectionProgressResource;
use App\Modules\Learning\Presentation\Http\Resource\LanguageProgressResource;
use App\Modules\Learning\Presentation\Http\Resource\SessionResource;
use App\Modules\Learning\Presentation\Http\Resource\StatsResource;
use App\Modules\Shared\Domain\ValueObject\UserId;
use DateTimeImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Study sessions (self-contained card packages), per-collection progress and dashboard stats. */
final class StudyController
{
    public function __construct(
        private readonly BuildStudySessionHandler $buildSession,
        private readonly CompleteStudySessionHandler $completeSession,
        private readonly GetUserStatsHandler $userStats,
        private readonly GetCollectionsProgressHandler $collectionsProgress,
        private readonly GetProgressByLanguageHandler $progressByLanguage,
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

    /**
     * Close a run the learner played to its summary. Always 200: this arrives through the client's
     * offline queue, so «already closed», «never started here» and «nothing was answered» are all
     * ordinary outcomes rather than errors — a 4xx would only teach the queue to retry forever.
     * `completed` says whether THIS call was the one that closed it.
     */
    public function complete(CompleteSessionRequest $request, string $sessionId): JsonResponse
    {
        $endedAt = $request->string('ended_at')->toString();

        $completed = ($this->completeSession)(new CompleteStudySession(
            actorId: $this->actorId($request),
            sessionId: StudySessionId::fromString($sessionId),
            endedAt: $endedAt !== '' ? new DateTimeImmutable($endedAt) : new DateTimeImmutable(),
        ));

        return new JsonResponse(['data' => ['completed' => $completed]]);
    }

    public function stats(Request $request): StatsResource
    {
        $stats = ($this->userStats)(new GetUserStats($this->actorId($request), new DateTimeImmutable()));

        return new StatsResource($stats);
    }

    /**
     * Per-collection progress, with the per-LANGUAGE cut beside it.
     *
     * `data` is unchanged, field for field — this endpoint is what the shelf and the collection
     * screen draw their bars from, and the cut is an ADDITION alongside them, never a reshaping of
     * them (DECISIONS п. 139). «Сколько усвоено в румынском» is a question about terms, and a term
     * has one language, so the cut regroups the very same rows rather than introducing a second
     * progress. There is deliberately no cut by PAIR: a word studied through two folders of
     * different support languages would be counted twice.
     */
    public function progress(Request $request): AnonymousResourceCollection
    {
        $actorId = $this->actorId($request);
        $now = new DateTimeImmutable();

        $progress = ($this->collectionsProgress)(new GetCollectionsProgress($actorId, $now));
        $byLanguage = ($this->progressByLanguage)(new GetProgressByLanguage($actorId, $now));

        return CollectionProgressResource::collection($progress)->additional([
            'by_language' => LanguageProgressResource::collection($byLanguage)->resolve($request),
        ]);
    }

    private function actorId(Request $request): UserId
    {
        return UserId::fromString((string) $request->user()?->getAuthIdentifier());
    }
}
