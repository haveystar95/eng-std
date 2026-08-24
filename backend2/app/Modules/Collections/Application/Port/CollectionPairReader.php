<?php

declare(strict_types=1);

namespace App\Modules\Collections\Application\Port;

use App\Modules\Collections\Application\Dto\CollectionPairView;
use App\Modules\Shared\Domain\ValueObject\UserId;

/**
 * WHICH LANGUAGE a term is being shown in, answered by the collection it is being shown through.
 *
 * The pair is a property of the collection (DECISIONS п. 81), so this is the reader every hot path
 * asks instead of asking the profile: the card in a session, the `/sync` mirror, the triage queue,
 * the collection screen. The profile used to answer this question, which meant a word saved in an
 * `en→ro` folder was glossed to a `ru` profile in Russian — or in nothing at all (реестр п. 137,
 * `search-language-pair.md` D11).
 *
 * Two questions, because the callers genuinely have two different contexts:
 *
 *  - {@see pairFor()} — the caller already knows WHICH collection (a scoped session, a triage
 *    queue, a generation request). One pair for every term it handles.
 *  - {@see supportLangByTerm()} — the caller has terms and no collection: a mixed session drawn
 *    from the pool, the `/sync` delta, the day-plan simulator. Each term answers for itself.
 */
interface CollectionPairReader
{
    /** Null when the collection does not exist or is deleted. Not access-checked — the caller has. */
    public function pairFor(string $collectionId): ?CollectionPairView;

    /**
     * The SUPPORT language each of these terms is shown in for this user — read off the collection
     * the term sits in.
     *
     * A term may sit in several of the user's collections, and then several pairs are true at once.
     * The choice is DETERMINISTIC and is the term's FIRST collection by `collections.id` — a ULID,
     * so «the folder this word arrived in», the same tie-break `term_examples` already carried
     * implicitly before A-1 gave the gloss a language of its own. Deterministic matters more than
     * clever here: the alternative is a word whose translation changes between two requests.
     *
     * Only collections the user actually studies count — owned ∪ actively subscribed, the same
     * access rule as {@see UserCollectionTermsReader}. A term in NO such collection is absent from
     * the result; the caller decides what to do with it (see
     * {@see \App\Modules\Learning\Application\Service\CardLanguageResolver}).
     *
     * @param  list<string>  $termIds
     * @return array<string, string>  term id => support language code
     */
    public function supportLangByTerm(UserId $userId, array $termIds): array;
}
