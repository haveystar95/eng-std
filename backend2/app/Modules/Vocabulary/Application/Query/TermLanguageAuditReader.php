<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Application\Query;

use App\Modules\Vocabulary\Application\Dto\TermLanguageRow;

/**
 * Every learner-language string a user can actually reach, for a language audit.
 *
 * Scoped to terms that sit in at least one collection on purpose. Terms nobody can reach are not a
 * user-visible defect and repairing them costs model calls for nothing — 118 of the 140 poisoned
 * terms in the live database are orphans (docs/ua-audit.md).
 *
 * It deliberately does NOT decide what is wrong: it returns rows and what each row claims to be,
 * and the caller judges them with the one shared detector. A reader that filtered by a regex here
 * would be a second, silently diverging copy of the rule.
 */
interface TermLanguageAuditReader
{
    /**
     * @param  string  $sourceLang  the learner language the reachable collections are built for
     * @return list<TermLanguageRow>
     */
    public function reachableRows(string $sourceLang): array;
}
