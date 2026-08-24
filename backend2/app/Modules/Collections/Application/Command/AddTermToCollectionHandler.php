<?php

declare(strict_types=1);

namespace App\Modules\Collections\Application\Command;

use App\Modules\Collections\Domain\Exception\CollectionNotFound;
use App\Modules\Collections\Domain\Repository\CollectionRepository;
use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Vocabulary\Application\Query\TermLanguageReader;

/**
 * The door EVERY existing term goes through on its way into a folder: the collection screen's «add
 * by id», a finished generation, the recovery of lost terms, and the save from search. Which is why
 * the pair invariant is checked by the aggregate this calls and not by any of them.
 */
final readonly class AddTermToCollectionHandler
{
    public function __construct(
        private CollectionRepository $collections,
        private TermLanguageReader $termLangs,
    ) {}

    public function __invoke(AddTermToCollection $command): void
    {
        $collection = $this->collections->findById($command->collectionId)
            ?? throw CollectionNotFound::withId($command->collectionId);

        $collection->assertEditableBy($command->actorId);
        $collection->addTerm($command->termId, $this->langOf($command->termId, $collection->targetLang()), $command->note);

        $this->collections->save($collection);
    }

    /**
     * A term id that names no live term reads as «the collection's own language», so the gate lets
     * it through and the write fails where it always did — on the foreign key. Inventing a mismatch
     * for a term that does not exist would answer «wrong language» to a question about a missing
     * word.
     */
    private function langOf(TermId $termId, LanguageCode $fallback): LanguageCode
    {
        $lang = $this->termLangs->langsFor([$termId])[$termId->value] ?? null;

        return $lang !== null ? new LanguageCode($lang) : $fallback;
    }
}
