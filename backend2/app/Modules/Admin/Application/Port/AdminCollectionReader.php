<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Port;

use App\Modules\Admin\Application\Dto\CollectionDetail;
use App\Modules\Admin\Application\Dto\CollectionRow;
use App\Modules\Admin\Application\Dto\ListWindow;
use App\Modules\Admin\Application\Dto\Page;

interface AdminCollectionReader
{
    /** @return Page<CollectionRow> */
    public function list(?string $type, ?string $search, ListWindow $window): Page;

    public function detail(string $collectionId): ?CollectionDetail;
}
