<?php

declare(strict_types=1);

namespace App\Modules\Generation\Domain\Service;

/**
 * Chooses which of a collection's terms become the dialog's target words, and in what order.
 * Priority mirrors what a learner most needs to rehearse out loud:
 *   1. learning/due — terms already started whose review interval has elapsed;
 *   2. new — terms never studied yet;
 *   3. known — started terms that aren't due.
 * Pure: the caller resolves the three inputs from Learning; this only orders and caps them.
 */
final class TargetWordPrioritizer
{
    /**
     * @param  list<string>  $collectionTermIds   all collection terms, in presentation order
     * @param  list<string>  $dueTermIds          due terms among the collection, soonest first
     * @param  array<string, true>  $startedTermIds  lookup of terms with a non-`new` progress row
     * @return list<string>  at most $limit term ids, in priority order, de-duplicated
     */
    public function select(array $collectionTermIds, array $dueTermIds, array $startedTermIds, int $limit): array
    {
        $due = [];
        foreach ($dueTermIds as $id) {
            if (in_array($id, $collectionTermIds, true)) {
                $due[$id] = true; // keep due order, restrict to this collection
            }
        }

        $new = [];
        $known = [];
        foreach ($collectionTermIds as $id) {
            if (isset($due[$id])) {
                continue;
            }
            if (isset($startedTermIds[$id])) {
                $known[$id] = true;
            } else {
                $new[$id] = true;
            }
        }

        $ordered = [...array_keys($due), ...array_keys($new), ...array_keys($known)];

        return array_slice($ordered, 0, max(0, $limit));
    }
}
