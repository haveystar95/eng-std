<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Application\Query;

use App\Modules\Vocabulary\Application\Dto\TermSearchRow;

/**
 * The free half of search: what the database already knows.
 *
 * Instant and costs nothing, which is the whole reason it runs first — a word that has been
 * generated once, for anybody, must never be paid for again. What it returns is ordered by how
 * closely the hit matches: exact first, then prefix, and the term's own text before its
 * translations (someone typing «bank» means the English word far more often than they mean a hit on
 * the Russian «банк» of another term).
 */
interface TermSearchReader
{
    /**
     * @param  string  $query       what the learner typed, raw — normalisation is this reader's job
     * @param  string  $lang        the language being learned; only terms in it are searched
     * @param  string  $nativeLang  the learner's language — which translations are searched and shown
     * @return list<TermSearchRow>  best match first, at most `$limit`
     */
    public function search(string $query, string $lang, string $nativeLang, int $limit = 20): array;
}
