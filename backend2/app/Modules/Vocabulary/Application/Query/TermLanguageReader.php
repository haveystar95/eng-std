<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Application\Query;

use App\Modules\Shared\Domain\ValueObject\TermId;

/**
 * WHICH LANGUAGE a term is in — the one fact a writer needs before it may put that term into a
 * collection, and the one fact only Vocabulary can answer.
 *
 * The sibling of {@see TermExistenceReader}, and needed for the same reason: Collections has to
 * decide something about a term without joining Vocabulary's tables. Here the decision is the pair
 * invariant ({@see \App\Modules\Collections\Domain\ValueObject\LanguagePair}).
 */
interface TermLanguageReader
{
    /**
     * @param  list<TermId>  $termIds
     * @return array<string, string>  term id => language code; a term that does not exist is absent
     */
    public function langsFor(array $termIds): array;
}
