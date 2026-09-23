<?php

declare(strict_types=1);

namespace App\Modules\Admin\Presentation\Http\Controller;

use App\Modules\Admin\Application\Port\AdminUserReader;
use App\Modules\Admin\Application\Query\GetPlanAudio;
use App\Modules\Admin\Application\Query\GetPlanAudioHandler;
use App\Modules\Admin\Application\Query\GetPlanCalls;
use App\Modules\Admin\Application\Query\GetPlanCallsHandler;
use App\Modules\Admin\Application\Query\GetPlanPage;
use App\Modules\Admin\Application\Query\GetPlanPageHandler;
use App\Modules\Admin\Application\Query\ListLearnerPlans;
use App\Modules\Admin\Application\Query\ListLearnerPlansHandler;
use App\Modules\Admin\Application\Query\PlanCodeConflict;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * THE LEARNER'S PLAN PAGE (наряд ADM-1) — read-only, one aggregating endpoint per section of the page, each answering for
 * the whole plan or for one day (`?day=N`). The plan is named by its code (`NKKGFF` — characters 5–10 of its ULID) or its
 * full id; a code two plans share is 409 with both ids. No route here writes, buys or queues anything.
 */
final class PlanPageController
{
    private const MAX_DAY = 10;

    private const DEFAULT_LIMIT = 50;

    private const MAX_LIMIT = 100;

    public function __construct(
        private readonly GetPlanPageHandler $pages,
        private readonly GetPlanCallsHandler $calls,
        private readonly GetPlanAudioHandler $audio,
        private readonly ListLearnerPlansHandler $learnerPlans,
        private readonly AdminUserReader $users,
    ) {}

    public function learnerPlans(string $id): JsonResponse
    {
        abort_if($this->users->profile($id) === null, Response::HTTP_NOT_FOUND);

        return response()->json(['data' => ($this->learnerPlans)(new ListLearnerPlans($id))]);
    }

    public function show(string $code): JsonResponse
    {
        return $this->page(new GetPlanPage($code));
    }

    public function issues(Request $request, string $code): JsonResponse
    {
        return $this->page(new GetPlanPage($code, 'issues', $this->day($request)));
    }

    public function days(Request $request, string $code): JsonResponse
    {
        return $this->page(new GetPlanPage($code, 'days', $this->day($request)));
    }

    public function pipeline(Request $request, string $code): JsonResponse
    {
        return $this->page(new GetPlanPage($code, 'pipeline', $this->day($request)));
    }

    public function lesson(Request $request, string $code): JsonResponse
    {
        return $this->page(new GetPlanPage($code, 'lesson', $this->day($request)));
    }

    public function passage(Request $request, string $code): JsonResponse
    {
        return $this->page(new GetPlanPage($code, 'passage', $this->day($request)));
    }

    public function conversations(Request $request, string $code): JsonResponse
    {
        return $this->page(new GetPlanPage($code, 'conversations', $this->day($request)));
    }

    public function money(Request $request, string $code): JsonResponse
    {
        return $this->page(new GetPlanPage($code, 'money', $this->day($request)));
    }

    public function calls(Request $request, string $code): JsonResponse
    {
        $source = $request->string('source')->toString();
        if ($source !== '' && ! in_array($source, GetPlanCalls::SOURCES, true)) {
            abort(Response::HTTP_UNPROCESSABLE_ENTITY, 'source must be one of: '.implode(', ', GetPlanCalls::SOURCES));
        }
        $limit = $request->integer('limit', self::DEFAULT_LIMIT);
        if ($limit < 1 || $limit > self::MAX_LIMIT) {
            abort(Response::HTTP_UNPROCESSABLE_ENTITY, 'limit must be 1..'.self::MAX_LIMIT);
        }
        $cursor = $request->string('cursor')->toString();

        $result = $this->conflicts(fn (): ?array => ($this->calls)(new GetPlanCalls(
            $code, $this->day($request), $source === '' ? null : $source, $cursor === '' ? null : $cursor, $limit,
        )));
        abort_if($result === null, Response::HTTP_NOT_FOUND);

        return response()->json($result);
    }

    public function audio(string $code, string $audioId): Response
    {
        $audio = $this->conflicts(fn (): ?array => ($this->audio)(new GetPlanAudio($code, $audioId)));
        abort_if($audio === null, Response::HTTP_NOT_FOUND);

        return new Response($audio['bytes'], Response::HTTP_OK, [
            'Content-Type' => $audio['format'] === 'wav' ? 'audio/wav' : 'audio/mpeg',
            'Content-Length' => (string) strlen($audio['bytes']),
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    private function page(GetPlanPage $query): JsonResponse
    {
        $result = $this->conflicts(fn (): ?array => ($this->pages)($query));
        abort_if($result === null, Response::HTTP_NOT_FOUND);

        return response()->json($result);
    }

    private function day(Request $request): ?int
    {
        if (! $request->has('day') || $request->string('day')->toString() === '') {
            return null;
        }
        $day = filter_var($request->query('day'), FILTER_VALIDATE_INT);
        if ($day === false || $day < 1 || $day > self::MAX_DAY) {
            abort(Response::HTTP_UNPROCESSABLE_ENTITY, 'day must be 1..'.self::MAX_DAY);
        }

        return $day;
    }

    /**
     * @template T
     *
     * @param  callable(): T  $read
     * @return T
     */
    private function conflicts(callable $read): mixed
    {
        try {
            return $read();
        } catch (PlanCodeConflict $e) {
            throw new HttpException(Response::HTTP_CONFLICT, $e->getMessage().' — open the plan by its full id', $e);
        }
    }
}
