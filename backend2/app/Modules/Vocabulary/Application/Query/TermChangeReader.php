<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Application\Query;

use App\Modules\Vocabulary\Application\Dto\TermChangeRef;
use DateTimeImmutable;

/**
 * Which of the given terms changed in (since, upper], for delta sync. Scoped to an id set (the
 * user's collection terms) — the global dictionary is never synced. `since` null = all in the set
 * (full snapshot). Ordered by (updated_at, id) so an offset cursor pages deterministically.
 *
 * Detection is on terms.updated_at; content (translations/examples) is set at term creation in
 * this app, so a new term is caught. A later translation-only edit that doesn't bump the term row
 * would be missed — noted for when a term-content edit flow exists.
 */
interface TermChangeReader
{
    /**
     * @param  list<string>  $termIds
     * @return list<TermChangeRef>
     */
    public function changedTermIds(array $termIds, ?DateTimeImmutable $since, DateTimeImmutable $upper): array;
}
