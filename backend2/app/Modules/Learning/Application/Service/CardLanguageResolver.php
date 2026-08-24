<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Service;

use App\Modules\Collections\Application\Port\CollectionPairReader;
use App\Modules\Learning\Application\Port\LearnerProfileReader;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Shared\Domain\ValueObject\UserId;
use App\Modules\Vocabulary\Application\Dto\SupportLanguages;

/**
 * WHICH LANGUAGE this batch of cards is written in — the one question every hot path in Learning
 * used to answer with `profiles.native_language`.
 *
 * The answer is the pair of the collection the term is being shown THROUGH (DECISIONS п. 81), and
 * the two shapes below are the two contexts Learning is ever in:
 *
 *  - a session or a queue SCOPED to one collection — that collection's pair, for every card in it;
 *  - a session drawn from the pool, a `/sync` page, the day-plan simulator — the term's own
 *    collection, per term, because the pool legitimately mixes pairs (п. 128, 143).
 *
 * The profile survives here as exactly one thing: the language for a term that has NO collection
 * left to read a pair from. That is reachable and not exotic — deleting a folder never touched the
 * pool (п. 102), so a word can outlive its folder and still be due. Falling back to the owner's own
 * language there is the only honest answer available, and it is a DEFAULT, which is all the profile
 * is allowed to be (п. 142).
 */
final readonly class CardLanguageResolver
{
    public function __construct(
        private CollectionPairReader $pairs,
        private LearnerProfileReader $profile,
    ) {}

    /**
     * @param  list<TermId>  $termIds
     * @param  string|null  $collectionId  the collection this whole batch is being read through, if
     *                                     the caller has one. Its pair then answers for every term.
     */
    public function forTerms(UserId $user, array $termIds, ?string $collectionId = null): SupportLanguages
    {
        if ($collectionId !== null) {
            $pair = $this->pairs->pairFor($collectionId);
            if ($pair !== null) {
                return SupportLanguages::uniform($pair->sourceLang);
            }
            // A scope id that names no live collection: the caller's own access check will have
            // returned nothing anyway, so this is a batch of zero. Fall through to the per-term
            // path rather than invent a pair.
        }

        return SupportLanguages::perTerm(
            $this->pairs->supportLangByTerm($user, array_map(static fn (TermId $id): string => $id->value, $termIds)),
            $this->profile->nativeLangFor($user),
        );
    }
}
