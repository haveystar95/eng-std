<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Command;

use App\Modules\Collections\Application\Command\AddTermToCollection;
use App\Modules\Collections\Application\Command\AddTermToCollectionHandler;
use App\Modules\Collections\Application\Command\CreateGeneratedCollection;
use App\Modules\Collections\Application\Command\CreateGeneratedCollectionHandler;
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

        $brief = new GenerationBrief(
            prompt: $request->prompt(),
            sourceLang: $request->sourceLang(),
            targetLang: $request->targetLang(),
            levels: $request->levels(),
            size: $request->size(),
        );

        // Slow model call — deliberately outside any transaction.
        $draft = $this->validator->validate($this->generator->generate($brief), $brief);

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
