<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Application\Query;

use App\Modules\Vocabulary\Application\Dto\TermLanguageRow;

/**
 * Every learner-language string on record, for a language audit — split into the two scopes a
 * caller has to choose between, because they cost differently and mean differently:
 *
 *  - `reachableRows()`: terms that sit in at least one collection, so a bad row is a user-visible
 *    defect right now;
 *  - `orphanRows()`: terms in no collection at all — 118 of the 140 poisoned terms found by
 *    docs/ua-audit.md were exactly this. Not user-visible today, but a `migrate:fresh --seed`
 *    reseeds these terms right back into whatever collection reuses them next (docs/ua-audit.md
 *    §4, class C), so a cutover-before-rebuild cleanup has to reach them too.
 *
 * Neither method decides what is wrong: they return rows and what each row claims to be, and the
 * caller judges them with the one shared detector. A reader that filtered by a regex here would be
 * a second, silently diverging copy of the rule.
 */
interface TermLanguageAuditReader
{
    /**
     * @param  string  $sourceLang  the learner language the reachable collections are built for
     * @return list<TermLanguageRow>
     */
    public function reachableRows(string $sourceLang): array;

    /**
     * @param  string  $sourceLang  the learner language to judge every orphan's fields against
     * @return list<TermLanguageRow>
     */
    public function orphanRows(string $sourceLang): array;
}
