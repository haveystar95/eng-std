<?php

declare(strict_types=1);

namespace App\Modules\Admin\Presentation\Http\Controller;

use App\Modules\Admin\Application\Port\AdminDialogReader;
use App\Modules\Admin\Presentation\Http\AdminJson;
use App\Modules\Admin\Presentation\Http\Paging;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class PracticeDialogController
{
    public function __construct(private readonly AdminDialogReader $dialogs) {}

    public function index(Request $request): JsonResponse
    {
        [$page, $perPage] = Paging::of($request);
        $userId = $request->string('user_id')->toString();

        $result = $this->dialogs->list($userId !== '' ? $userId : null, $page, $perPage);

        return response()->json(AdminJson::page($result, AdminJson::dialogRow(...)));
    }

    public function show(string $id): JsonResponse
    {
        $detail = $this->dialogs->detail($id);
        abort_if($detail === null, Response::HTTP_NOT_FOUND);

        return response()->json(AdminJson::dialogDetail($detail));
    }
}
