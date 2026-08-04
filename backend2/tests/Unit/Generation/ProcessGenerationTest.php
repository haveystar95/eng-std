<?php

declare(strict_types=1);

use App\Modules\Collections\Application\Command\AddTermToCollectionHandler;
use App\Modules\Collections\Application\Command\CreateGeneratedCollectionHandler;
use App\Modules\Generation\Application\Command\FailGeneration;
use App\Modules\Generation\Application\Command\FailGenerationHandler;
use App\Modules\Generation\Application\Command\ProcessGeneration;
use App\Modules\Generation\Application\Command\ProcessGenerationHandler;
use App\Modules\Generation\Application\Command\RequestCollectionGeneration;
use App\Modules\Generation\Application\Command\RequestCollectionGenerationHandler;
use App\Modules\Generation\Application\Dto\GeneratedCollectionDraft;
use App\Modules\Generation\Application\Dto\GeneratedItem;
use App\Modules\Generation\Application\Dto\GenerationBrief;
use App\Modules\Generation\Application\Port\CollectionGeneratorPort;
use App\Modules\Generation\Application\Service\DraftValidator;
use App\Modules\Generation\Domain\Exception\GenerationQuotaExceeded;
use App\Modules\Generation\Domain\Exception\InvalidGeneratedDraft;
use App\Modules\Generation\Domain\Service\PromptNormalizer;
use App\Modules\Generation\Domain\ValueObject\GenerationStatus;
use App\Modules\Generation\Infrastructure\Adapter\FakeCollectionGenerator;
use App\Modules\Shared\Domain\ValueObject\GenerationRequestId;
use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use App\Modules\Shared\Domain\ValueObject\UserId;
use App\Modules\Vocabulary\Application\Command\FindOrCreateTermHandler;
use App\Modules\Vocabulary\Application\Command\ImportTermHandler;
use App\Modules\Vocabulary\Domain\Service\TermNormalizer;
use Tests\Doubles\FakeGenerationQuota;
use Tests\Doubles\FixedClock;
use Tests\Doubles\ImmediateTransactionManager;
use Tests\Doubles\InMemoryCollectionRepository;
use Tests\Doubles\InMemoryGenerationRequestRepository;
use Tests\Doubles\InMemoryTermRepository;

beforeEach(function () {
    $this->clock = new FixedClock(new DateTimeImmutable('2026-07-27T12:00:00Z'));
    $this->requests = new InMemoryGenerationRequestRepository();
    $this->terms = new InMemoryTermRepository();
    $this->collections = new InMemoryCollectionRepository();
    $this->user = UserId::generate();

    $findOrCreate = new FindOrCreateTermHandler($this->terms, new TermNormalizer(), $this->clock);
    $this->process = new ProcessGenerationHandler(
        requests: $this->requests,
        generator: new FakeCollectionGenerator(),
        validator: new DraftValidator(),
        importTerm: new ImportTermHandler($findOrCreate),
        createCollection: new CreateGeneratedCollectionHandler($this->collections, $this->clock),
        addTerm: new AddTermToCollectionHandler($this->collections),
        tx: new ImmediateTransactionManager(),
        clock: $this->clock,
    );
});

function openGeneration(object $ctx, int $used = 0): GenerationRequestId
{
    $handler = new RequestCollectionGenerationHandler(
        $ctx->requests, new FakeGenerationQuota($used), new PromptNormalizer(), $ctx->clock,
    );

    return $handler(new RequestCollectionGeneration(
        $ctx->user, 'иду в банк', new LanguageCode('ru'), new LanguageCode('en'), ['A2', 'B1'], 12,
    ));
}

it('materializes a collection with deduplicated terms from a pending request', function () {
    $id = openGeneration($this);

    ($this->process)(new ProcessGeneration($id));

    $request = $this->requests->findById($id);
    expect($request?->status())->toBe(GenerationStatus::Succeeded)
        ->and($request?->collectionId())->not->toBeNull()
        ->and($this->terms->count())->toBe(12)
        ->and($this->collections->count())->toBe(1);

    $collection = $this->collections->findById($request->collectionId());
    expect($collection?->itemsCount())->toBe(12)
        ->and($collection?->ownerId()?->value)->toBe($this->user->value);
});

it('is idempotent — reprocessing a finished request creates nothing new', function () {
    $id = openGeneration($this);

    ($this->process)(new ProcessGeneration($id));
    ($this->process)(new ProcessGeneration($id));

    expect($this->collections->count())->toBe(1);
});

it('rejects a new request once the daily quota is exhausted', function () {
    expect(fn () => openGeneration($this, RequestCollectionGenerationHandler::DAILY_LIMIT))
        ->toThrow(GenerationQuotaExceeded::class);
});

it('records tokens, cost and raw response even when the draft fails validation', function () {
    $id = openGeneration($this);

    // A generator whose draft can't pass validation (one item, well under the minimum), but which
    // still cost tokens — the spend and the raw output must survive the rejection.
    $badGenerator = new class implements CollectionGeneratorPort
    {
        public function generate(GenerationBrief $brief): GeneratedCollectionDraft
        {
            return new GeneratedCollectionDraft(
                title: 'x',
                description: null,
                items: [new GeneratedItem('only one', 'word', 'один', null, 'A2')],
                model: 'gpt-4o',
                tokensIn: 700,
                tokensOut: 1200,
                rawResponse: '{"truncated":true}',
            );
        }
    };

    $findOrCreate = new FindOrCreateTermHandler($this->terms, new TermNormalizer(), $this->clock);
    $process = new ProcessGenerationHandler(
        requests: $this->requests,
        generator: $badGenerator,
        validator: new DraftValidator(),
        importTerm: new ImportTermHandler($findOrCreate),
        createCollection: new CreateGeneratedCollectionHandler($this->collections, $this->clock),
        addTerm: new AddTermToCollectionHandler($this->collections),
        tx: new ImmediateTransactionManager(),
        clock: $this->clock,
    );

    expect(fn () => ($process)(new ProcessGeneration($id)))->toThrow(InvalidGeneratedDraft::class);

    // Not succeeded — it's the queue job that turns this into `failed` (no retry). But the usage
    // is already persisted, so a validation failure no longer vanishes from the spend model.
    $request = $this->requests->findById($id);
    expect($request?->status())->toBe(GenerationStatus::Running)
        ->and($request?->tokensIn())->toBe(700)
        ->and($request?->tokensOut())->toBe(1200)
        ->and($request?->costUsd())->toBe('0.013750')
        ->and($request?->rawResponse())->toBe('{"truncated":true}')
        ->and($this->collections->count())->toBe(0);
});

it('records a terminal failure via FailGeneration', function () {
    $id = openGeneration($this);

    (new FailGenerationHandler($this->requests, $this->clock))(new FailGeneration($id, 'boom'));

    expect($this->requests->findById($id)?->status())->toBe(GenerationStatus::Failed)
        ->and($this->requests->findById($id)?->error())->toBe('boom');
});
