<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Application\Query;

use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Vocabulary\Application\Dto\TermAnswerKeyView;

/**
 * Gives other modules the accepted target-language forms of a term so they can grade a
 * produced answer, without reaching into Vocabulary's tables. Returns a set per term.
 */
interface TermAnswerKeyReader
{
    /**
     * @param  list<TermId>  $termIds
     * @return array<string, TermAnswerKeyView>  keyed by term id
     */
    public function byIds(array $termIds): array;
}
