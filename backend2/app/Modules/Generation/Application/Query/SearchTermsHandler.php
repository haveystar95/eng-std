<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Query;

use App\Modules\Collections\Application\Port\TermFolderMembershipReader;
use App\Modules\Generation\Application\Dto\SearchHitView;
use App\Modules\Generation\Application\Service\LearnerLanguages;
use App\Modules\Vocabulary\Application\Dto\TermSearchRow;
use App\Modules\Vocabulary\Application\Query\TermSearchReader;

/**
 * The FREE half of search — what the database already has — composed with the one fact the learner
 * needs beside it: which of their own folders each hit is already in.
 *
 * It lives in Generation rather than in Vocabulary because of what it is the first half OF: the
 * whole point of running this query is to decide whether a paid lookup is needed, and «found /
 * not found» is a question about spending money. Keeping the two halves in one module is what stops
 * the free search and the paid one from drifting apart on how a query is normalised.
 *
 * @see LookupWordHandler for the half that costs something.
 */
final readonly class SearchTermsHandler
{
    public function __construct(
        private TermSearchReader $terms,
        private TermFolderMembershipReader $folders,
        private LearnerLanguages $languages,
    ) {}

    /** @return list<SearchHitView> */
    public function __invoke(SearchTerms $query): array
    {
        $langs = $this->languages->forUser($query->actorId);

        $hits = $this->terms->search(
            $query->query,
            $langs->target->value,
            $langs->native->value,
            $query->limit,
        );
        if ($hits === []) {
            return [];
        }

        $membership = $this->folders->foldersHolding(
            $query->actorId,
            array_map(static fn (TermSearchRow $row): string => $row->id, $hits),
        );

        return array_map(
            static fn (TermSearchRow $row): SearchHitView => new SearchHitView(
                termId: $row->id,
                text: $row->text,
                type: $row->type,
                transcription: $row->transcription,
                translation: $row->translation,
                description: $row->description,
                example: $row->example,
                exampleTranslation: $row->exampleTranslation,
                cefr: $row->cefr,
                folders: $membership[$row->id] ?? [],
            ),
            $hits,
        );
    }
}
