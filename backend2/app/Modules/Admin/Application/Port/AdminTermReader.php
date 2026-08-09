<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Port;

use App\Modules\Admin\Application\Dto\Page;
use App\Modules\Admin\Application\Dto\TermDetail;
use App\Modules\Admin\Application\Dto\TermRow;

interface AdminTermReader
{
    /** @return Page<TermRow> */
    public function list(?string $search, int $page, int $perPage): Page;

    public function detail(string $termId): ?TermDetail;
}
