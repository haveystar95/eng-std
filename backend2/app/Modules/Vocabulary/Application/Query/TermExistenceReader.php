<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Application\Query;

use App\Modules\Shared\Domain\ValueObject\TermId;

/**
 * Read model letting other modules confirm which term ids actually exist, without
 * reaching into Vocabulary's tables. Learning uses it to reject reviews for unknown terms.
 */
interface TermExistenceReader
{
    /**
     * Filter a set of term ids down to those that exist.
     *
     * @param  list<TermId>  $termIds
     * @return list<TermId>
     */
    public function existing(array $termIds): array;
}
