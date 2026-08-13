<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Query;

use App\Modules\Shared\Domain\ValueObject\CollectionId;

/**
 * Which language the станок (and the proofreading export) should read a term's translation in for
 * these collections: the deck's own `source_lang`.
 *
 * A query of its own, in Generation's Application, for the same reason
 * {@see ListPendingEnrichmentTargets} is here: the callers are a queue job and a console command,
 * and neither may reach Collections directly.
 *
 * @param  list<CollectionId>  $collectionIds  the first readable one decides; in practice a run names
 *                                             decks that teach from the same language.
 */
final readonly class GetCollectionTranslationLang
{
    /** @param  list<CollectionId>  $collectionIds */
    public function __construct(public array $collectionIds) {}
}
