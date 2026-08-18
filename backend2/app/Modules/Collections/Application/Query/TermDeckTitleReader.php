<?php

declare(strict_types=1);

namespace App\Modules\Collections\Application\Query;

/**
 * Which LIVE decks each of these terms sits in, by title.
 *
 * Collections owns `collection_items` and `collections`, so this is the only way another module may
 * ask the question. A reporting query that joined those tables from outside would be a second place
 * that knows what «live» means here — soft-deleted items and soft-deleted collections both count as
 * gone — and the two would drift the first time that definition moved.
 */
interface TermDeckTitleReader
{
    /**
     * @param  list<string>  $termIds
     * @return array<string, list<string>>  term id => deck titles, terms in no deck simply absent
     */
    public function titlesFor(array $termIds): array;
}
