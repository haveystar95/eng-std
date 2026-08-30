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
     * @param  string|null  $scopeCollectionId  the collection this batch is being read THROUGH, when
     *                        the caller has one. A term may carry an example written for one
     *                        particular collection (`term_examples.scope_collection_id` — a plan day
     *                        writes one per term, in that day's situation), and this is what decides
     *                        whether the learner sees it. Null means «no scope»: the term's own
     *                        general example, and a scoped one only when the term has nothing else.
     * @return array<string, TermContentView>  keyed by term id
     */
    public function byIds(array $termIds, SupportLanguages $langs, ?string $scopeCollectionId = null): array;
}
