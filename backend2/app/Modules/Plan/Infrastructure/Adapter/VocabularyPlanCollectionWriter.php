<?php

declare(strict_types=1);

namespace App\Modules\Plan\Infrastructure\Adapter;

use App\Modules\Collections\Application\Command\AddTermToCollection;
use App\Modules\Collections\Application\Command\AddTermToCollectionHandler;
use App\Modules\Collections\Application\Command\CreateGeneratedCollection;
use App\Modules\Collections\Application\Command\CreateGeneratedCollectionHandler;
use App\Modules\Plan\Application\Port\PlanCollectionWriter;
use App\Modules\Plan\Domain\Entity\Plan;
use App\Modules\Plan\Domain\ValueObject\TermKind;
use App\Modules\Shared\Domain\ValueObject\CollectionId;
use App\Modules\Vocabulary\Application\Command\ImportTerm;
use App\Modules\Vocabulary\Application\Command\ImportTermHandler;
use App\Modules\Vocabulary\Application\Dto\ExampleInput;
use App\Modules\Vocabulary\Application\Dto\TranslationInput;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The plan's collection through the two modules that own the mechanism: Vocabulary dedups and
 * writes the term (source `ai`, stamped with the lesson prompt), Collections files it under the
 * plan's folder (origin `plan`, so it never shows as one of the learner's own shelves).
 */
final readonly class VocabularyPlanCollectionWriter implements PlanCollectionWriter
{
    public function __construct(
        private CreateGeneratedCollectionHandler $createCollection,
        private ImportTermHandler $importTerm,
        private AddTermToCollectionHandler $addTerm,
    ) {}

    public function ensureCollection(Plan $plan): CollectionId
    {
        $existing = $plan->collectionId();
        if ($existing !== null) {
            return $existing;
        }
        $titles = $plan->titles();

        return ($this->createCollection)(new CreateGeneratedCollection(
            ownerId: $plan->userId(),
            title: $titles->titleNative ?? mb_substr($plan->goalText(), 0, 60),
            sourceLang: $plan->nativeLang(),
            targetLang: $plan->targetLang(),
            description: $plan->goalText(),
            topic: $plan->goalText(),
            imageApiPrompt: $titles?->coverImagePrompt,
            origin: CreateGeneratedCollection::ORIGIN_PLAN,
        ));
    }

    public function addTerms(Plan $plan, CollectionId $collectionId, array $terms, string $promptVersion): void
    {
        foreach ($terms as $term) {
            try {
                $termId = ($this->importTerm)(new ImportTerm(
                    lang: $plan->targetLang(),
                    text: $term->textTarget(),
                    type: $term->kind()->vocabularyType(),
                    pos: null,
                    source: 'ai',
                    translations: [new TranslationInput($plan->nativeLang(), $term->textNative(), true)],
                    examples: $term->exampleTarget() === null ? [] : [new ExampleInput($term->exampleTarget(), $term->exampleNative(), $plan->nativeLang())],
                    imageApiPrompt: $term->kind() === TermKind::Phrase ? null : $term->imagePrompt(),
                    promptVersion: $promptVersion !== '' ? $promptVersion : null,
                    generationModel: null,
                ));
                ($this->addTerm)(new AddTermToCollection($collectionId, $termId, $plan->userId()));
            } catch (Throwable $e) {
                // One bad term must not hold the day's close; the rest of the sheet still lands.
                Log::warning('plan term not added to collection', ['term' => $term->textTarget(), 'error' => $e->getMessage()]);
            }
        }
    }
}
