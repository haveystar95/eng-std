<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\Repository;

use App\Modules\Learning\Domain\Entity\Triage;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Shared\Domain\ValueObject\UserId;

interface TriageRepository
{
    /**
     * Append a triage to the log. Idempotent by the client-generated id (ON CONFLICT DO
     * NOTHING); returns true when the row was newly inserted, false when it already existed.
     */
    public function insertIgnore(Triage $triage): bool;

    /**
     * The governing (current) triage per term for this user: the row with the greatest
     * client_seq across the whole log — never `decided_at`, which is a device clock. Used to
     * re-project progress after a batch, so an out-of-order or clock-skewed swipe can't win.
     *
     * @param  list<TermId>  $termIds
     * @return array<string, Triage>  term id → its governing triage (terms with no row are absent)
     */
    public function currentByTerm(UserId $userId, array $termIds): array;
}
