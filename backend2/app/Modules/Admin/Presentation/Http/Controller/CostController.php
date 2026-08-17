<?php

declare(strict_types=1);

namespace App\Modules\Admin\Presentation\Http\Controller;

use App\Modules\Admin\Application\Port\AdminCostReader;
use App\Modules\Admin\Presentation\Http\AdminJson;
use DateTimeImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Unit economics. Two questions, two endpoints: "what did this collection cost me" and "what did
 * everything cost this week".
 */
final class CostController
{
    public function __construct(private readonly AdminCostReader $costs) {}

    public function collection(string $id): JsonResponse
    {
        $view = $this->costs->collectionCost($id);
        abort_if($view === null, Response::HTTP_NOT_FOUND);

        return response()->json(AdminJson::costByPurpose($view));
    }

    public function summary(Request $request): JsonResponse
    {
        $period = $request->string('period')->toString();
        if ($period === '') {
            $period = 'week';
        }

        $since = match ($period) {
            'day' => new DateTimeImmutable('-1 day'),
            'week' => new DateTimeImmutable('-7 days'),
            'month' => new DateTimeImmutable('-30 days'),
            'all' => null,
            default => abort(Response::HTTP_UNPROCESSABLE_ENTITY, 'period must be day|week|month|all'),
        };

        return response()->json(AdminJson::costByPurpose($this->costs->costByPurposeSince($since, $period)));
    }
}
