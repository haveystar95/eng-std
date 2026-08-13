<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Application\Query;

use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Vocabulary\Application\Dto\TermContentView;

/**
 * Batch-hydrates renderable term content for other modules (Collections detail, Study
 * session cards) so nobody joins the terms tables from outside Vocabulary.
 */
interface TermContentReader
{
    /**
     * @param  list<TermId>  $termIds
     * @param  string  $lang  the LEARNER's language (profiles.native_language) — which of the term's
     *                        translations is the question on the card. Required rather than defaulted:
     *                        a caller that forgets it is exactly how a Russian speaker got asked in
     *                        Ukrainian, and a default would let the next caller forget it silently.
     * @return array<string, TermContentView>  keyed by term id
     */
    public function byIds(array $termIds, string $lang): array;
}
