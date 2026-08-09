<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Port;

use App\Modules\Admin\Application\Dto\Page;
use App\Modules\Admin\Application\Dto\RequestLogRow;

interface AdminRequestLogReader
{
    /** @return Page<RequestLogRow> */
    public function list(?string $userId, ?int $status, ?string $path, int $page, int $perPage): Page;
}
