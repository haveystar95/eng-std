<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Query;

use App\Modules\Collections\Application\Port\TermFolderMembershipReader;
use App\Modules\Generation\Application\Dto\SearchHitView;
use App\Modules\Generation\Application\Service\SearchPair;
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
        private SearchPair $pair,
    ) {}

    /** @return list<SearchHitView> */
    public function __invoke(SearchTerms $query): array
    {
        $pair = $this->pair->resolve($query->actorId, $query->source, $query->target);

        // The CATALOGUE sides, not the direction. «Похожие» are the same rows whichever way the
        // learner is asking — `case` is an English term with a Russian translation either way — and
        // a reader handed the direction would go looking for Russian terms and find none.
        $hits = $this->terms->search(
            $query->query,
            $pair->termLang,
            $pair->translationLang,
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
