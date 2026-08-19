<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Command;

use App\Modules\Collections\Application\Command\AddTermToCollection;
use App\Modules\Collections\Application\Command\AddTermToCollectionHandler;
use App\Modules\Collections\Application\Command\CreateGeneratedCollection;
use App\Modules\Collections\Application\Command\CreateGeneratedCollectionHandler;
use App\Modules\Collections\Application\Dto\CollectionTermSetView;
use App\Modules\Collections\Application\Query\GetCollectionTermSet;
use App\Modules\Collections\Application\Query\GetCollectionTermSetHandler;
use App\Modules\Generation\Application\Dto\AttemptUsage;
use App\Modules\Generation\Application\Dto\GeneratedCollectionDraft;
use App\Modules\Generation\Application\Dto\GenerationBrief;
use App\Modules\Generation\Application\Port\CollectionGeneratorPort;
use App\Modules\Generation\Application\Port\DispatchesEnrichment;
use App\Modules\Generation\Application\Port\DispatchesExampleRepair;
use App\Modules\Generation\Application\Port\DispatchesImageAttachment;
use App\Modules\Generation\Application\Port\RecordsGenerationRejections;
use App\Modules\Generation\Application\Service\DraftValidator;
use App\Modules\Generation\Application\Service\GenerationPipeline;
use App\Modules\Generation\Application\Service\LanguageBarrier;
use App\Modules\Generation\Domain\Entity\GenerationRequest;
use App\Modules\Generation\Domain\Exception\GenerationRequestNotFound;
use App\Modules\Generation\Domain\Repository\GenerationRequestRepository;
use App\Modules\Shared\Domain\Service\Clock;
use App\Modules\Shared\Domain\Service\TransactionManager;
use App\Modules\Shared\Domain\ValueObject\CollectionId;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Vocabulary\Application\Command\ImportTerm;
use App\Modules\Vocabulary\Application\Command\ImportTermHandler;
use App\Modules\Vocabulary\Application\Dto\ExampleInput;
use App\Modules\Vocabulary\Application\Dto\TranslationInput;

/**
 * The heavy step, run in the background (or inline from the console). Talks to Vocabulary
 * and Collections only through their Application commands — never their tables. The slow
 * model call happens outside the DB transaction; only the writes are transactional.
 *
 * Idempotent: a re-run of an already-finished request is a no-op. Exceptions propagate so
 * the queue can retry; terminal failure is recorded by {@see FailGenerationHandler}.
 */
final readonly class ProcessGenerationHandler
{
    /**
     * The overshoot + top-up + summed-spend logic lives in {@see GenerationPipeline} so the eval tool
     * measures the exact same behaviour. Built from the injected generator + validator, keeping this
     * handler's constructor stable for callers and tests.
     */
    private GenerationPipeline $pipeline;

    public function __construct(
        private GenerationRequestRepository $requests,
        CollectionGeneratorPort $generator,
        DraftValidator $validator,
        LanguageBarrier $barrier,
        private RecordsGenerationRejections $rejections,
        private ImportTermHandler $importTerm,
        private CreateGeneratedCollectionHandler $createCollection,
        private AddTermToCollectionHandler $addTerm,
        private GetCollectionTermSetHandler $cachedTermSet,
        private DispatchesImageAttachment $attachImages,
        private DispatchesEnrichment $enrich,
        private DispatchesExampleRepair $repairExamples,
        private TransactionManager $tx,
        private Clock $clock,
    ) {
        $this->pipeline = new GenerationPipeline($generator, $validator, $barrier);
    }

    public function __invoke(ProcessGeneration $command): void
    {
        $request = $this->requests->findById($command->id)
            ?? throw GenerationRequestNotFound::withId($command->id);

        if ($request->status()->isTerminal()) {
            return;
        }

        $request->markRunning();
        $this->requests->save($request);

        // Prompt cache: an identical prompt (same normalized text, language pair, prompt version)
        // already produced a term set — reuse it and skip the model entirely. Collections are
        // personal, terms are shared, so we clone the terms into a fresh collection for this user.
        // A prompt_version bump misses on purpose, forcing a regeneration.
        $cachedCollectionId = $this->requests->findCacheableCollection(
            $request->normalizedPrompt(),
            $request->sourceLang(),
            $request->targetLang(),
            $request->promptVersion(),
        );
        if ($cachedCollectionId !== null) {
            $termSet = ($this->cachedTermSet)(new GetCollectionTermSet($cachedCollectionId));
            if ($termSet !== null && $termSet->termIds !== []) {
                $collectionId = $this->tx->run(fn (): CollectionId => $this->materializeFromCache($request, $termSet));
                $request->markSucceeded(
                    collectionId: $collectionId,
                    model: 'cache',
                    tokensIn: 0,
                    tokensOut: 0,
                    costUsd: '0.000000',
                    deliveredCount: count($termSet->termIds),
                    finishedAt: $this->clock->now(),
                );
                $this->requests->save($request);

                // Reused terms already carry photos (globally shared); only the fresh personal
                // collection needs its cover searched — the job's readers skip everything else.
                $this->attachImages->dispatch($collectionId);
                // Cheap on this path: the terms are reused, so the version mark usually makes it a
                // no-op. Chained anyway, because "reused" does not guarantee "already enriched".
                $this->chainEnrichment($collectionId);

                return;
            }
        }

        // Generate → validate → (optional) top-up, all in the shared pipeline. The callback persists
        // spend the instant each model call answers, before validation can reject the draft: a rejected
        // draft still cost tokens, and a top-up's spend is summed onto the primary's, never overwritten.
        // A broken primary draft throws InvalidGeneratedDraft here — terminal, no retry (the queue job
        // turns it into `failed`), with the recorded spend intact.
        $assembled = $this->pipeline->assemble(
            $this->requestedBrief($request),
            function (AttemptUsage $usage) use ($request): void {
                $request->recordAttempt(
                    model: $usage->model,
                    tokensIn: $usage->tokensIn,
                    tokensOut: $usage->tokensOut,
                    costUsd: $usage->costUsd,
                    rawResponse: $usage->rawResponse,
                );
                $this->requests->save($request);
            },
        );

        // What the language barrier refused, written before the collection so a crash between the
        // two loses the collection (recoverable — the user regenerates) rather than the evidence of
        // WHY it was short (not recoverable — the model output is gone). No-op when nothing was
        // refused, which is the normal case.
        $this->rejections->record($request->id()->value, $assembled->rejections);

        // Materialize the *final accepted* set (after filter + top-up). This is also what the prompt
        // cache stores, so the next identical prompt reuses the fixed-up set, not the raw under-delivery.
        $collectionId = $this->tx->run(fn (): CollectionId => $this->materialize($request, $assembled->draft, $assembled->model));

        $request->markSucceeded(
            collectionId: $collectionId,
            model: $assembled->model,
            tokensIn: $assembled->tokensIn,
            tokensOut: $assembled->tokensOut,
            costUsd: $assembled->costUsd,
            deliveredCount: $assembled->delivered,
            finishedAt: $this->clock->now(),
        );
        $this->requests->save($request);

        // Fire-and-forget: attach photos to the new terms + cover, off the generation thread.
        $this->attachImages->dispatch($collectionId);
        $this->chainEnrichment($collectionId);
        // …and give a real example to whatever the validator refused for merely repeating its term
        // (QA-7). Same shape as the two chains above: it must not be able to slow this down or fail
        // it, and a collection missing an example or two is still a complete collection.
        $this->repairExamples->repairCollection($collectionId, $request->userId());
    }

    /**
     * Queue the enrichment станок for the finished collection — accepted variants (which offline
     * grading needs) and distractors. Same shape as the image chain and for the same reason: it runs
     * AFTER the generation the user is waiting on, on its own job, so it can neither slow that down
     * nor fail it. A collection without variants is still a complete, playable collection.
     *
     * Whether the chain is switched on at all is the adapter's business (it reads the config) — this
     * layer only says that a finished generation is the moment to enrich.
     */
    private function chainEnrichment(CollectionId $collectionId): void
    {
        $this->enrich->enrichCollection($collectionId->value, BuildTermEnrichmentsHandler::VERSION);
    }

    private function requestedBrief(GenerationRequest $request): GenerationBrief
    {
        return new GenerationBrief(
            prompt: $request->prompt(),
            sourceLang: $request->sourceLang(),
            targetLang: $request->targetLang(),
            levels: $request->levels(),
            size: $request->size(),
        );
    }

    /**
     * @param  string|null  $model  the model that actually answered — from the assembled draft, not
     *                              from config: a top-up or a repair can land on a different one,
     *                              and the row must say which one wrote it.
     */
    private function materialize(GenerationRequest $request, GeneratedCollectionDraft $draft, ?string $model = null): CollectionId
    {
        $collectionId = ($this->createCollection)(new CreateGeneratedCollection(
            ownerId: $request->userId(),
            title: $draft->title,
            sourceLang: $request->sourceLang(),
            targetLang: $request->targetLang(),
            description: $draft->description,
            topic: $request->prompt(),
            imageApiPrompt: $draft->imageApiPrompt,   // cover-image query for AttachImagesJob
        ));

        foreach ($draft->items as $item) {
            $termId = ($this->importTerm)(new ImportTerm(
                lang: $request->targetLang(),
                text: $item->text,
                type: $item->type,
                pos: null,
                source: 'ai',
                translations: [new TranslationInput($request->sourceLang(), $item->translation, isPrimary: true)],
                ipa: $item->transcription,
                examples: $item->example !== null
                    ? [new ExampleInput($item->example, $item->exampleTranslation)]
                    : [],
                cefr: $item->cefr,
                imageApiPrompt: $item->imageApiPrompt,   // per-term image query for AttachImagesJob
                promptVersion: $request->promptVersion(),
                generationModel: $model,
            ));

            ($this->addTerm)(new AddTermToCollection($collectionId, $termId, $request->userId()));
        }

        return $collectionId;
    }

    private function materializeFromCache(GenerationRequest $request, CollectionTermSetView $termSet): CollectionId
    {
        $collectionId = ($this->createCollection)(new CreateGeneratedCollection(
            ownerId: $request->userId(),
            title: $termSet->title,
            sourceLang: $request->sourceLang(),
            targetLang: $request->targetLang(),
            description: $termSet->description,
            topic: $request->prompt(),
            imageApiPrompt: $termSet->imageApiPrompt,   // copied so the cache-hit collection can search its own cover
        ));

        // Terms already exist globally (they were created by the original generation) — just link
        // them into this user's fresh collection. No ImportTerm, no model call.
        foreach ($termSet->termIds as $termIdValue) {
            ($this->addTerm)(new AddTermToCollection($collectionId, TermId::fromString($termIdValue), $request->userId()));
        }

        return $collectionId;
    }
}
