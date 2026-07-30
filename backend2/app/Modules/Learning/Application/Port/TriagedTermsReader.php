<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Port;

use App\Modules\Shared\Domain\ValueObject\UserId;

/**
 * Tells which of a set of terms the user has already triaged (has any row in term_triages).
 * The triage queue excludes these so a term is never swiped twice — distinct from the study
 * "new" pool, which only cares whether a progress row exists.
 */
interface TriagedTermsReader
{
    /**
     * @param  list<string>  $termIds
     * @return array<string, true>  the subset that already has a triage record, as a lookup set
     */
    public function triagedTermIds(UserId $userId, array $termIds): array;
}
