<?php

declare(strict_types=1);

namespace App\Modules\Admin\Presentation\Http\Controller;

use App\Modules\Admin\Application\Dto\LogFilters;
use App\Modules\Admin\Application\Port\AdminRequestLogReader;
use App\Modules\Admin\Presentation\Http\AdminJson;
use App\Modules\Admin\Presentation\Http\Paging;
use App\Modules\Admin\Presentation\Http\Request\ListLogsRequest;
use DateTimeImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The request log, sliced. Every filter is a query parameter and nothing else — that is what lets
 * the panel keep its filter state in the URL and a shared link reproduce the exact same slice.
 *
 * The list omits bodies (a page of 50 OpenAI round-trips is megabytes); `show` returns the full,
 * already-redacted request and response for the one row the operator expanded.
 */
final class RequestLogController
{
    public function __construct(private readonly AdminRequestLogReader $logs) {}

    public function index(ListLogsRequest $request): JsonResponse
    {
        $window = Paging::of($request);

        $result = $this->logs->list(
            new LogFilters(
                direction: $this->str($request, 'direction'),
                provider: $this->str($request, 'provider'),
                status: $request->has('status') ? $request->integer('status') : null,
                statusClass: $this->str($request, 'status_class'),
                purpose: $this->str($request, 'purpose'),
                userId: $this->str($request, 'user_id'),
                collectionId: $this->str($request, 'collection_id'),
                from: $this->date($request, 'from'),
                to: $this->date($request, 'to'),
                path: $this->str($request, 'path'),
                search: $this->str($request, 'search'),
            ),
            $window,
        );

        return response()->json(AdminJson::page($result, AdminJson::requestLog(...)));
    }

    public function show(string $id): JsonResponse
    {
        $detail = $this->logs->detail($id);
        abort_if($detail === null, Response::HTTP_NOT_FOUND);

        return response()->json(AdminJson::requestLogDetail($detail));
    }

    private function str(Request $request, string $key): ?string
    {
        $value = $request->string($key)->toString();

        return $value !== '' ? $value : null;
    }

    /**
     * Normalise a validated date to a form Postgres always accepts. The raw query value can arrive
     * with its `+00:00` offset eaten by a copy-pasted URL, which the driver then refuses.
     */
    private function date(Request $request, string $key): ?string
    {
        $raw = $this->str($request, $key);
        if ($raw === null) {
            return null;
        }

        return (new DateTimeImmutable($raw))->format('Y-m-d H:i:sP');
    }
}
