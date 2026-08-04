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
use App\Modules\Generation\Application\Dto\GeneratedCollectionDraft;
use App\Modules\Generation\Application\Dto\GenerationBrief;
use App\Modules\Generation\Application\Port\CollectionGeneratorPort;
use App\Modules\Generation\Application\Service\DraftValidator;
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
    /** Rough USD per 1K tokens (input, output), for the spend read model. */
    private const PRICING = [
        'gpt-4o' => [0.0025, 0.01],
        'gpt-4o-mini' => [0.00015, 0.0006],
    ];

    public function __construct(
        private GenerationRequestRepository $requests,
        private CollectionGeneratorPort $generator,
        private DraftValidator $validator,
        private ImportTermHandler $importTerm,
        private CreateGeneratedCollectionHandler $createCollection,
        private AddTermToCollectionHandler $addTerm,
        private GetCollectionTermSetHandler $cachedTermSet,
        private TransactionManager $tx,
        private Clock $clock,
    ) {}

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
                    finishedAt: $this->clock->now(),
                );
                $this->requests->save($request);

                return;
            }
        }

        $brief = new GenerationBrief(
            prompt: $request->prompt(),
            sourceLang: $request->sourceLang(),
            targetLang: $request->targetLang(),
            levels: $request->levels(),
            size: $request->size(),
        );

        // Slow model call — deliberately outside any transaction. A transient transport error
        // bubbles up so the queue can retry; the validation below is deterministic and must not.
        $raw = $this->generator->generate($brief);

        // Persist the spend + raw output the instant the model answers, before validation can
        // reject the draft: a rejected draft still cost tokens, and its raw response is what we
        // need to diagnose the rejection. markSucceeded re-sets the same usage on the happy path.
        $request->recordAttempt(
            model: $raw->model,
            tokensIn: $raw->tokensIn,
            tokensOut: $raw->tokensOut,
            costUsd: $this->estimateCost($raw->model, $raw->tokensIn, $raw->tokensOut),
            rawResponse: $raw->rawResponse,
        );
        $this->requests->save($request);

        $draft = $this->validator->validate($raw, $brief);

        $collectionId = $this->tx->run(fn (): CollectionId => $this->materialize($request, $draft));

        $request->markSucceeded(
            collectionId: $collectionId,
            model: $draft->model,
            tokensIn: $draft->tokensIn,
            tokensOut: $draft->tokensOut,
            costUsd: $this->estimateCost($draft->model, $draft->tokensIn, $draft->tokensOut),
            finishedAt: $this->clock->now(),
        );
        $this->requests->save($request);
    }

    private function materialize(GenerationRequest $request, GeneratedCollectionDraft $draft): CollectionId
    {
        $collectionId = ($this->createCollection)(new CreateGeneratedCollection(
            ownerId: $request->userId(),
            title: $draft->title,
            sourceLang: $request->sourceLang(),
            targetLang: $request->targetLang(),
            description: $draft->description,
            topic: $request->prompt(),
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
        ));

        // Terms already exist globally (they were created by the original generation) — just link
        // them into this user's fresh collection. No ImportTerm, no model call.
        foreach ($termSet->termIds as $termIdValue) {
            ($this->addTerm)(new AddTermToCollection($collectionId, TermId::fromString($termIdValue), $request->userId()));
        }

        return $collectionId;
    }

    private function estimateCost(string $model, ?int $tokensIn, ?int $tokensOut): ?string
    {
        if (! isset(self::PRICING[$model]) || $tokensIn === null || $tokensOut === null) {
            return null;
        }

        [$inRate, $outRate] = self::PRICING[$model];
        $cost = ($tokensIn / 1000) * $inRate + ($tokensOut / 1000) * $outRate;

        return number_format($cost, 6, '.', '');
    }
}
