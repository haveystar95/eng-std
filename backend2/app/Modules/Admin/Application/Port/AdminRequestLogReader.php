<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Port;

use App\Modules\Admin\Application\Dto\ListWindow;
use App\Modules\Admin\Application\Dto\LogFilters;
use App\Modules\Admin\Application\Dto\Page;
use App\Modules\Admin\Application\Dto\RequestLogDetail;
use App\Modules\Admin\Application\Dto\RequestLogRow;

interface AdminRequestLogReader
{
    /** @return Page<RequestLogRow> */
    public function list(LogFilters $filters, ListWindow $window): Page;

    /** The same row plus its full (already-redacted) headers and bodies. */
    public function detail(string $id): ?RequestLogDetail;

    /**
     * The most recent failed outbound calls (5xx or a transport error) — the dashboard's
     * "what is broken right now" strip.
     *
     * @return list<RequestLogRow>
     */
    public function recentFailures(int $limit): array;
}
