<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\Repository;

use App\Modules\Learning\Domain\Entity\Triage;

interface TriageRepository
{
    /**
     * Append a triage to the log. Idempotent by the client-generated id (ON CONFLICT DO
     * NOTHING); returns true when the row was newly inserted, false when it already existed.
     */
    public function insertIgnore(Triage $triage): bool;
}
