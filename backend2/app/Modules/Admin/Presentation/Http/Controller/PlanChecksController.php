<?php

declare(strict_types=1);

namespace App\Modules\Admin\Presentation\Http\Controller;

use App\Modules\Admin\Application\Dto\PlanCheckRow;
use App\Modules\Admin\Application\Query\GetPlanChecks;
use App\Modules\Admin\Application\Query\GetPlanChecksHandler;
use Illuminate\Http\JsonResponse;

/** «Проверки плана»: the counters per prompt version, read-only — the modes themselves live in config. */
final class PlanChecksController
{
    public function __construct(private readonly GetPlanChecksHandler $checks) {}

    public function index(): JsonResponse
    {
        $rows = ($this->checks)(new GetPlanChecks);

        return response()->json([
            'data' => array_map(static fn (PlanCheckRow $r): array => [
                'prompt_version' => $r->promptVersion,
                'check' => $r->check,
                'action' => $r->action,
                'hits' => $r->hits,
            ], $rows),
        ]);
    }
}
