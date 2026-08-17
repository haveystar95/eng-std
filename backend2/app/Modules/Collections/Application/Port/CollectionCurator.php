<?php

declare(strict_types=1);

namespace App\Modules\Collections\Application\Port;

use App\Modules\Collections\Application\Dto\CollectionImpact;
use App\Modules\Shared\Domain\ValueObject\CollectionId;
use App\Modules\Shared\Domain\ValueObject\TermId;

/**
 * Back-office writes over a collection, with no user actor.
 *
 * The user-facing commands next door all go through `assertEditableBy($userId)` — correct for a
 * learner editing their own deck, and useless here: a store collection has no owner at all, and
 * curating one is exactly the job. Authorisation is the admin guard on the route.
 */
interface CollectionCurator
{
    /** Who would feel a change to this collection. Null when there is no such live collection. */
    public function impact(CollectionId $collectionId): ?CollectionImpact;

    /** Title/description. Null = leave alone. False when the collection does not exist. */
    public function updateDetails(CollectionId $collectionId, ?string $title, ?string $description): bool;

    /** Add a term that already exists in the dictionary. False when either side is missing. */
    public function addTerm(CollectionId $collectionId, TermId $termId): bool;

    /** Remove a term from THIS collection only; the term itself stays in the dictionary. */
    public function removeTerm(CollectionId $collectionId, TermId $termId): bool;

    /**
     * Delete the collection for everyone: soft-delete it AND end every subscription.
     *
     * Both halves are needed for the delta to reach every client. An owner learns from the
     * collection's own tombstone; a subscriber learns from theirs — and their subscription row has
     * to be stamped rather than deleted, because a row that is simply gone produces no delta at
     * all and the deck would linger on their phone forever.
     */
    public function purge(CollectionId $collectionId): bool;
}
