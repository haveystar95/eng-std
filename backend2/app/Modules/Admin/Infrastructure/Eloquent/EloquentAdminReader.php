<?php

declare(strict_types=1);

namespace App\Modules\Admin\Infrastructure\Eloquent;

use App\Modules\Admin\Application\Dto\AdminView;
use App\Modules\Admin\Application\Port\AdminReader;

final class EloquentAdminReader implements AdminReader
{
    public function byId(string $id): ?AdminView
    {
        $admin = Admin::query()->find($id);

        return $admin !== null ? new AdminView($admin->id, $admin->email, $admin->name) : null;
    }
}
