<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Port;

use App\Modules\Admin\Application\Dto\AdminUserCollectionRow;
use App\Modules\Admin\Application\Dto\AdminUserProfileRow;
use App\Modules\Admin\Application\Dto\AdminUserRow;
use App\Modules\Admin\Application\Dto\Page;
use App\Modules\Admin\Application\Dto\ProgressStateCounts;

interface AdminUserReader
{
    /** @return Page<AdminUserRow> */
    public function list(?string $search, int $page, int $perPage): Page;

    public function profile(string $userId): ?AdminUserProfileRow;

    public function progressStates(string $userId): ProgressStateCounts;

    public function reviewsTotal(string $userId): int;

    /** @return list<AdminUserCollectionRow> */
    public function collectionsOf(string $userId): array;
}
