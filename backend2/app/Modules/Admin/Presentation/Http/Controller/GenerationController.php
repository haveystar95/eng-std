<?php

declare(strict_types=1);

namespace App\Modules\Admin\Presentation\Http\Controller;

use App\Modules\Admin\Application\Port\AdminGenerationReader;
use App\Modules\Admin\Presentation\Http\AdminJson;
use App\Modules\Admin\Presentation\Http\Paging;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class GenerationController
{
    public function __construct(private readonly AdminGenerationReader $generations) {}

    public function index(Request $request): JsonResponse
    {
        $window = Paging::of($request);
        $userId = $request->string('user_id')->toString();
        $status = $request->string('status')->toString();

        $result = $this->generations->list(
            $userId !== '' ? $userId : null,
            $status !== '' ? $status : null,
            $window,
        );

        return response()->json(AdminJson::page($result, AdminJson::generation(...)));
    }
}
