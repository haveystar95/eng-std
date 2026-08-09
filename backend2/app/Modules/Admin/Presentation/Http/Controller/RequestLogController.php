<?php

declare(strict_types=1);

namespace App\Modules\Admin\Presentation\Http\Controller;

use App\Modules\Admin\Application\Port\AdminRequestLogReader;
use App\Modules\Admin\Presentation\Http\AdminJson;
use App\Modules\Admin\Presentation\Http\Paging;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class RequestLogController
{
    public function __construct(private readonly AdminRequestLogReader $logs) {}

    public function index(Request $request): JsonResponse
    {
        [$page, $perPage] = Paging::of($request);
        $userId = $request->string('user_id')->toString();
        $path = $request->string('path')->toString();
        $status = $request->query('status');

        $result = $this->logs->list(
            $userId !== '' ? $userId : null,
            is_numeric($status) ? (int) $status : null,
            $path !== '' ? $path : null,
            $page,
            $perPage,
        );

        return response()->json(AdminJson::page($result, AdminJson::requestLog(...)));
    }
}
