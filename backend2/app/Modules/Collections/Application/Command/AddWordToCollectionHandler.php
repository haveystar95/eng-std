<?php

declare(strict_types=1);

namespace App\Modules\Collections\Application\Command;

use App\Modules\Collections\Domain\Exception\CollectionNotFound;
use App\Modules\Collections\Domain\Repository\CollectionRepository;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Vocabulary\Application\Command\ImportTerm;
use App\Modules\Vocabulary\Application\Command\ImportTermHandler;
use App\Modules\Vocabulary\Application\Dto\TranslationInput;
use App\Modules\Vocabulary\Application\Port\DispatchesTermEnrichment;

final readonly class AddWordToCollectionHandler
{
    public function __construct(
        private CollectionRepository $collections,
        private ImportTermHandler $importTerm,
        private DispatchesTermEnrichment $enrichment,
    ) {}

    public function __invoke(AddWordToCollection $command): TermId
    {
        $collection = $this->collections->findById($command->collectionId)
            ?? throw CollectionNotFound::withId($command->collectionId);

        // Check ownership before creating a term for a forbidden operation.
        $collection->assertEditableBy($command->actorId);

        $translation = $command->translation !== null ? trim($command->translation) : '';
        $translations = $translation !== ''
            ? [new TranslationInput($collection->sourceLang(), $translation, isPrimary: true)]
            : [];

        $termId = ($this->importTerm)(new ImportTerm(
            lang: $collection->targetLang(),
            text: $command->text,
            type: $command->type,
            pos: null,
            source: 'user',
            translations: $translations,
        ));

        $collection->addTerm($termId);
        $this->collections->save($collection);

        // No translation given → the LLM fills in translation/transcription/example/photo. The
        // translation target is the collection's source (native) language.
        if ($translations === []) {
            $this->enrichment->enrich($termId, $collection->sourceLang());
        }

        return $termId;
    }
}
