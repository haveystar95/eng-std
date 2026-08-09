<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Port;

use App\Modules\Admin\Application\Dto\Page;
use App\Modules\Admin\Application\Dto\ReviewRow;

interface AdminReviewReader
{
    /**
     * @param  ?string  $from  ISO date/time lower bound on answered_at (inclusive)
     * @param  ?string  $to    ISO date/time upper bound on answered_at (inclusive)
     * @return Page<ReviewRow>
     */
    public function list(string $userId, ?string $from, ?string $to, int $page, int $perPage): Page;
}
