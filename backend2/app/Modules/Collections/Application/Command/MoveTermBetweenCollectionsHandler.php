<?php

declare(strict_types=1);

namespace App\Modules\Collections\Application\Command;

use App\Modules\Collections\Domain\Exception\CollectionNotFound;
use App\Modules\Collections\Domain\Repository\CollectionRepository;
use App\Modules\Shared\Domain\Service\TransactionManager;
use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use App\Modules\Vocabulary\Application\Query\TermLanguageReader;

final readonly class MoveTermBetweenCollectionsHandler
{
    public function __construct(
        private CollectionRepository $collections,
        private TransactionManager $tx,
        private TermLanguageReader $termLangs,
    ) {}

    public function __invoke(MoveTermBetweenCollections $command): void
    {
        if ($command->fromCollectionId->equals($command->toCollectionId)) {
            return; // moving a word to the folder it is already in is a no-op, not an error
        }

        $this->tx->run(function () use ($command): void {
            $from = $this->collections->findById($command->fromCollectionId)
                ?? throw CollectionNotFound::withId($command->fromCollectionId);
            $to = $this->collections->findById($command->toCollectionId)
                ?? throw CollectionNotFound::withId($command->toCollectionId);

            // BOTH ends, before anything is written: a move that checked only the source could push
            // a word into a folder the actor does not own, and one that checked only the target
            // could pull it out of a store deck.
            $from->assertEditableBy($command->actorId);
            $to->assertEditableBy($command->actorId);

            // Idempotent for an offline retry: `addTerm` ignores a term already present and
            // `removeTerm` a term already gone, so re-sending the same move changes nothing.
            //
            // The pair gate fires INSIDE the transaction and before the removal, so a word refused
            // by the destination is still in the folder it started in — a move between folders of
            // different pairs fails whole rather than losing the word on the way.
            $lang = $this->termLangs->langsFor([$command->termId])[$command->termId->value] ?? null;
            $to->addTerm($command->termId, $lang !== null ? new LanguageCode($lang) : $to->targetLang());
            $from->removeTerm($command->termId);

            $this->collections->save($to);
            $this->collections->save($from);
        });
    }
}
