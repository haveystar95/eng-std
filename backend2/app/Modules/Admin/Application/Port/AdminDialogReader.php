<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Port;

use App\Modules\Admin\Application\Dto\DialogDetail;
use App\Modules\Admin\Application\Dto\DialogRow;
use App\Modules\Admin\Application\Dto\Page;

interface AdminDialogReader
{
    /** @return Page<DialogRow> */
    public function list(?string $userId, int $page, int $perPage): Page;

    public function detail(string $dialogId): ?DialogDetail;
}
