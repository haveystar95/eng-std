<?php

declare(strict_types=1);

namespace App\Modules\Admin\Presentation\Http\Controller;

use App\Modules\Admin\Application\Query\GetDashboardHandler;
use App\Modules\Admin\Presentation\Http\AdminJson;
use Illuminate\Http\JsonResponse;

final class DashboardController
{
    public function __construct(private readonly GetDashboardHandler $dashboard) {}

    public function index(): JsonResponse
    {
        return response()->json(AdminJson::dashboard(($this->dashboard)()));
    }
}
