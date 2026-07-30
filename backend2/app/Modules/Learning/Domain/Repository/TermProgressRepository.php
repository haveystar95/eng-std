<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\Repository;

use App\Modules\Learning\Domain\Entity\TermProgress;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Shared\Domain\ValueObject\UserId;

interface TermProgressRepository
{
    /**
     * Load progress for scheduling. Implementations lock the (user, term) row for the
     * enclosing transaction so concurrent devices fold reviews in a consistent order.
     */
    public function findForUpdate(UserId $userId, TermId $termId): ?TermProgress;

    public function save(TermProgress $progress): void;

    /**
     * Drop the (user, term) progress row so the term reverts to "new" (no row = never
     * studied). Used when a `known` term is returned to learning from triage.
     */
    public function delete(UserId $userId, TermId $termId): void;
}
