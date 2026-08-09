<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Port;

use App\Modules\Admin\Application\Dto\AdminView;

interface AdminReader
{
    public function byId(string $id): ?AdminView;
}
