<?php

declare(strict_types=1);

namespace App\Modules\Admin\Presentation\Http\Controller;

use App\Modules\Admin\Application\Port\AdminReviewReader;
use App\Modules\Admin\Application\Port\AdminUserReader;
use App\Modules\Admin\Application\Query\GetUserCollections;
use App\Modules\Admin\Application\Query\GetUserCollectionsHandler;
use App\Modules\Admin\Application\Query\GetUserDetail;
use App\Modules\Admin\Application\Query\GetUserDetailHandler;
use App\Modules\Admin\Application\Query\GetUserPlan;
use App\Modules\Admin\Application\Query\GetUserPlanHandler;
use App\Modules\Admin\Presentation\Http\AdminJson;
use App\Modules\Admin\Presentation\Http\Paging;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** Read-only: users list, user detail, the day-plan simulator, and a user's review feed. */
final class UserController
{
    public function __construct(
        private readonly AdminUserReader $users,
        private readonly GetUserDetailHandler $userDetail,
        private readonly GetUserPlanHandler $userPlan,
        private readonly GetUserCollectionsHandler $userCollections,
        private readonly AdminReviewReader $reviews,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $search = $request->string('search')->toString();
        [$page, $perPage] = Paging::of($request);

        $result = $this->users->list($search !== '' ? $search : null, $page, $perPage);

        return response()->json(AdminJson::page($result, AdminJson::userRow(...)));
    }

    public function show(string $id): JsonResponse
    {
        $view = ($this->userDetail)(new GetUserDetail($this->userId($id)));
        abort_if($view === null, Response::HTTP_NOT_FOUND);

        return response()->json(AdminJson::userDetail($view));
    }

    public function plan(Request $request, string $id): JsonResponse
    {
        $userId = $this->userId($id);
        abort_if($this->users->profile($userId->value) === null, Response::HTTP_NOT_FOUND);

        $date = $request->string('date')->toString();
        if ($date !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
            abort(Response::HTTP_UNPROCESSABLE_ENTITY, 'date must be YYYY-MM-DD');
        }

        $plan = ($this->userPlan)(new GetUserPlan($userId, $date !== '' ? $date : null));

        return response()->json(AdminJson::dayPlan($plan));
    }

    public function collections(string $id): JsonResponse
    {
        $result = ($this->userCollections)(new GetUserCollections($this->userId($id)));
        abort_if($result === null, Response::HTTP_NOT_FOUND);

        return response()->json(['data' => array_map(AdminJson::userCollectionWithProgress(...), $result)]);
    }

    public function reviews(Request $request, string $id): JsonResponse
    {
        $userId = $this->userId($id);
        [$page, $perPage] = Paging::of($request);
        $from = $request->string('from')->toString();
        $to = $request->string('to')->toString();

        $result = $this->reviews->list(
            $userId->value,
            $from !== '' ? $from : null,
            $to !== '' ? $to : null,
            $page,
            $perPage,
        );

        return response()->json(AdminJson::page($result, AdminJson::review(...)));
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
