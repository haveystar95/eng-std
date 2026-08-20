<?php

declare(strict_types=1);

namespace App\Modules\Admin\Presentation\Http\Controller;

use App\Modules\Admin\Application\Query\GetCollectionContentHealth;
use App\Modules\Admin\Application\Query\GetCollectionContentHealthHandler;
use App\Modules\Admin\Application\Query\GetContentHealthSummary;
use App\Modules\Admin\Application\Query\GetContentHealthSummaryHandler;
use App\Modules\Admin\Application\Query\GetTermContentPassport;
use App\Modules\Admin\Application\Query\GetTermContentPassportHandler;
use App\Modules\Admin\Presentation\Http\AdminJson;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * «Здоровье контента»: what the dictionary is actually stocked with, and which trainers that stock
 * can build a card for.
 *
 * READ-ONLY, all of it, and one route short of what an operator might expect: there is no «запустить
 * станок» button anywhere here. The догон is handed over as a line to paste into a terminal, because
 * it spends money against a model and the panel is not the place to authorise that.
 *
 * This is also NOT the ladder screen, and the two must not be confused: `/ladder` answers «когда
 * этот тренажёр откроется ученику», which is a fact about progress and the admission matrix. This
 * answers «сможет ли карточка вообще собраться из контента термина», which is a fact about the
 * content and nothing else. A term can be perfect here and still never be dealt — and the cure for
 * each is in a different place.
 */
final class ContentHealthController
{
    public function __construct(
        private readonly GetContentHealthSummaryHandler $summary,
        private readonly GetCollectionContentHealthHandler $collection,
        private readonly GetTermContentPassportHandler $term,
    ) {}

    public function summary(): JsonResponse
    {
        return response()->json(AdminJson::contentHealthSummary(($this->summary)(new GetContentHealthSummary())));
    }

    public function collection(string $id): JsonResponse
    {
        $view = ($this->collection)(new GetCollectionContentHealth($id));
        abort_if($view === null, Response::HTTP_NOT_FOUND);

        return response()->json(AdminJson::collectionContentHealth($view));
    }

    /** One term's passport: what it holds, and what each of the ten trainers can build from it. */
    public function term(string $id): JsonResponse
    {
        $view = ($this->term)(new GetTermContentPassport($id));
        abort_if($view === null, Response::HTTP_NOT_FOUND);

        return response()->json(AdminJson::termContentPassport($view));
    }
}
