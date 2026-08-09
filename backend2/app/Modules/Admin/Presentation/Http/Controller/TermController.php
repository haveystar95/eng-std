<?php

declare(strict_types=1);

namespace App\Modules\Admin\Presentation\Http\Controller;

use App\Modules\Admin\Application\Port\AdminTermReader;
use App\Modules\Admin\Presentation\Http\AdminJson;
use App\Modules\Admin\Presentation\Http\Paging;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class TermController
{
    public function __construct(private readonly AdminTermReader $terms) {}

    public function index(Request $request): JsonResponse
    {
        [$page, $perPage] = Paging::of($request);
        $search = $request->string('search')->toString();

        $result = $this->terms->list($search !== '' ? $search : null, $page, $perPage);

        return response()->json(AdminJson::page($result, AdminJson::termRow(...)));
    }

    public function show(string $id): JsonResponse
    {
        $detail = $this->terms->detail($id);
        abort_if($detail === null, Response::HTTP_NOT_FOUND);

        return response()->json(AdminJson::termDetail($detail));
    }
}
