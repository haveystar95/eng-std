<?php

declare(strict_types=1);

namespace App\Modules\Admin\Presentation\Http\Controller;

use App\Modules\Admin\Application\Port\AdminCollectionReader;
use App\Modules\Admin\Presentation\Http\AdminJson;
use App\Modules\Admin\Presentation\Http\Paging;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class CollectionController
{
    public function __construct(private readonly AdminCollectionReader $collections) {}

    public function index(Request $request): JsonResponse
    {
        $window = Paging::of($request);
        $type = $request->string('type')->toString();
        $search = $request->string('search')->toString();

        $result = $this->collections->list(
            $type !== '' ? $type : null,
            $search !== '' ? $search : null,
            $window,
        );

        return response()->json(AdminJson::page($result, AdminJson::collectionRow(...)));
    }

    public function show(string $id): JsonResponse
    {
        $detail = $this->collections->detail($id);
        abort_if($detail === null, Response::HTTP_NOT_FOUND);

        return response()->json(AdminJson::collectionDetail($detail));
    }
}
