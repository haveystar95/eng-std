<?php

declare(strict_types=1);

namespace App\Modules\Admin\Presentation\Http\Controller;

use App\Modules\Admin\Application\Dto\AdminLadderFilter;
use App\Modules\Admin\Application\Port\AdminLadderReader;
use App\Modules\Admin\Application\Port\AdminUserReader;
use App\Modules\Admin\Application\Query\GetLadderProgress;
use App\Modules\Admin\Application\Query\GetLadderProgressHandler;
use App\Modules\Admin\Presentation\Http\AdminJson;
use App\Modules\Admin\Presentation\Http\Paging;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The acquisition ladder, watched live: where each of a learner's words stands, and what their
 * phone has just done. READ-ONLY — no route here writes anything, by design. The screen that
 * MOVES the ladder (the admission matrix) is a separate surface; this one only observes, which is
 * what makes it safe to leave open and polling while a session is being tested on a device.
 */
final class LadderController
{
    private const DEFAULT_EVENTS = 50;
    private const MAX_EVENTS = 200;
    private const MAX_LEARNERS = 200;

    public function __construct(
        private readonly AdminLadderReader $ladder,
        private readonly AdminUserReader $users,
        private readonly GetLadderProgressHandler $progress,
    ) {}

    /** The selector: who to watch, most recently active first. */
    public function learners(Request $request): JsonResponse
    {
        $limit = min(self::MAX_LEARNERS, max(1, $request->integer('limit', self::MAX_LEARNERS)));

        return response()->json([
            'data' => array_map(AdminJson::ladderLearner(...), $this->ladder->learners($limit)),
        ]);
    }

    /** One learner's pairs, with the phase split of the same moment. */
    public function progress(Request $request, string $id): JsonResponse
    {
        $userId = $this->userId($id);
        abort_if($this->users->profile($userId->value) === null, Response::HTTP_NOT_FOUND);

        $phase = $request->string('phase')->toString();
        if ($phase !== '' && ! in_array($phase, AdminLadderFilter::PHASES, true)) {
            abort(Response::HTTP_UNPROCESSABLE_ENTITY, 'phase must be one of: ' . implode(', ', AdminLadderFilter::PHASES));
        }
        $collectionId = $request->string('collection_id')->toString();

        $view = ($this->progress)(new GetLadderProgress(
            userId: $userId,
            filter: new AdminLadderFilter(
                collectionId: $collectionId !== '' ? $collectionId : null,
                phase: $phase !== '' ? $phase : null,
                dueOnly: $request->boolean('due'),
            ),
            window: Paging::of($request),
        ));

        return response()->json(AdminJson::ladderProgress($view));
    }

    /** The live feed: answers and intros in one stream, newest first. */
    public function events(Request $request, string $id): JsonResponse
    {
        $userId = $this->userId($id);
        abort_if($this->users->profile($userId->value) === null, Response::HTTP_NOT_FOUND);

        $limit = min(self::MAX_EVENTS, max(1, $request->integer('limit', self::DEFAULT_EVENTS)));

        return response()->json([
            'data' => array_map(AdminJson::ladderEvent(...), $this->ladder->events($userId->value, $limit)),
        ]);
    }

    private function userId(string $id): UserId
    {
        try {
            return UserId::fromString($id);
        } catch (InvalidArgumentException $e) {
            throw new NotFoundHttpException(previous: $e);
        }
    }
}
