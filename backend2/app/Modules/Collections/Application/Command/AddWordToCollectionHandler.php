<?php

declare(strict_types=1);

namespace App\Modules\Collections\Application\Command;

use App\Modules\Collections\Domain\Exception\CollectionNotFound;
use App\Modules\Collections\Domain\Repository\CollectionRepository;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Vocabulary\Application\Command\ImportTerm;
use App\Modules\Vocabulary\Application\Command\ImportTermHandler;
use App\Modules\Vocabulary\Application\Dto\TranslationInput;

final readonly class AddWordToCollectionHandler
{
    public function __construct(
        private CollectionRepository $collections,
        private ImportTermHandler $importTerm,
    ) {}

    public function __invoke(AddWordToCollection $command): TermId
    {
        $collection = $this->collections->findById($command->collectionId)
            ?? throw CollectionNotFound::withId($command->collectionId);

        // Check ownership before creating a term for a forbidden operation.
        $collection->assertEditableBy($command->actorId);

        $termId = ($this->importTerm)(new ImportTerm(
            lang: $collection->targetLang(),
            text: $command->text,
            type: $command->type,
            pos: null,
            source: 'user',
            translations: [new TranslationInput($collection->sourceLang(), $command->translation, isPrimary: true)],
        ));

        $collection->addTerm($termId);
        $this->collections->save($collection);

        return $termId;
    }
}
