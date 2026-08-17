<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Port;

use App\Modules\Admin\Application\Dto\GenerationRow;
use App\Modules\Admin\Application\Dto\ListWindow;
use App\Modules\Admin\Application\Dto\Page;

interface AdminGenerationReader
{
    /** @return Page<GenerationRow> */
    public function list(?string $userId, ?string $status, ListWindow $window): Page;
}
