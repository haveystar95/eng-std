<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Application\Query;

use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Vocabulary\Application\Dto\SupportLanguages;
use App\Modules\Vocabulary\Application\Dto\TermContentView;

/**
 * Batch-hydrates renderable term content for other modules (Collections detail, Study
 * session cards) so nobody joins the terms tables from outside Vocabulary.
 */
interface TermContentReader
{
    /**
     * @param  list<TermId>  $termIds
     * @param  SupportLanguages  $langs  which support language each term is read in — the pair of
     *                        the COLLECTION it is being shown through, never the reader's profile
     *                        (DECISIONS пп. 81, 142). Required rather than defaulted, and a
     *                        per-term answer rather than one scalar: a caller that forgets it is
     *                        exactly how a Russian speaker got asked in Ukrainian, and a session
     *                        that legitimately mixes two pairs has no single answer to give.
     * @return array<string, TermContentView>  keyed by term id
     */
    public function byIds(array $termIds, SupportLanguages $langs): array;
}
