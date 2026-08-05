<?php

declare(strict_types=1);

use App\Modules\Collections\Application\Command\AddTermToCollectionHandler;
use App\Modules\Collections\Application\Command\CreateGeneratedCollectionHandler;
use App\Modules\Collections\Application\Query\GetCollectionTermSetHandler;
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
use App\Modules\Generation\Domain\Service\GenerationDailyLimit;
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
use Tests\Doubles\FakeUserTierReader;
use Tests\Doubles\FixedClock;
use Tests\Doubles\ImmediateTransactionManager;
use Tests\Doubles\InMemoryCollectionRepository;
use Tests\Doubles\InMemoryGenerationRequestRepository;
use Tests\Doubles\InMemoryTermRepository;
use Tests\Doubles\RecordingImageAttachmentDispatcher;

beforeEach(function () {
    $this->clock = new FixedClock(new DateTimeImmutable('2026-07-27T12:00:00Z'));
    $this->requests = new InMemoryGenerationRequestRepository();
    $this->terms = new InMemoryTermRepository();
    $this->collections = new InMemoryCollectionRepository();
    $this->attach = new RecordingImageAttachmentDispatcher();
    $this->user = UserId::generate();

    $findOrCreate = new FindOrCreateTermHandler($this->terms, new TermNormalizer(), $this->clock);
    $this->process = new ProcessGenerationHandler(
        requests: $this->requests,
        generator: new FakeCollectionGenerator(),
        validator: new DraftValidator(),
        importTerm: new ImportTermHandler($findOrCreate),
        createCollection: new CreateGeneratedCollectionHandler($this->collections, $this->clock),
        addTerm: new AddTermToCollectionHandler($this->collections),
        cachedTermSet: new GetCollectionTermSetHandler($this->collections),
        attachImages: $this->attach,
        tx: new ImmediateTransactionManager(),
        clock: $this->clock,
    );
});

function openGeneration(object $ctx, int $used = 0): GenerationRequestId
{
    $handler = new RequestCollectionGenerationHandler(
        $ctx->requests, new FakeGenerationQuota($used), new PromptNormalizer(), $ctx->clock,
        new FakeUserTierReader(), new GenerationDailyLimit(),
    );

    return $handler(new RequestCollectionGeneration(
        $ctx->user, 'иду в банк', new LanguageCode('ru'), new LanguageCode('en'), ['A2', 'B1'], 12,
    ));
}

/** Build a ProcessGenerationHandler wired to a specific generator (the rest are shared fakes). */
function processWith(object $ctx, CollectionGeneratorPort $generator): ProcessGenerationHandler
{
    $findOrCreate = new FindOrCreateTermHandler($ctx->terms, new TermNormalizer(), $ctx->clock);

    return new ProcessGenerationHandler(
        requests: $ctx->requests,
        generator: $generator,
        validator: new DraftValidator(),
        importTerm: new ImportTermHandler($findOrCreate),
        createCollection: new CreateGeneratedCollectionHandler($ctx->collections, $ctx->clock),
        addTerm: new AddTermToCollectionHandler($ctx->collections),
        cachedTermSet: new GetCollectionTermSetHandler($ctx->collections),
        attachImages: new RecordingImageAttachmentDispatcher(),
        tx: new ImmediateTransactionManager(),
        clock: $ctx->clock,
    );
}

/**
 * A generator scripted per call: the first response is the primary pass, the second is the top-up.
 * Each entry is [items, tokensIn, tokensOut]. Records how many times it was called.
 */
function scriptedGenerator(array $responses): CollectionGeneratorPort
{
    return new class($responses) implements CollectionGeneratorPort
    {
        public int $calls = 0;

        /** @param list<array{0: list<GeneratedItem>, 1: int, 2: int}> $responses */
        public function __construct(private array $responses) {}

        public function generate(GenerationBrief $brief): GeneratedCollectionDraft
        {
            [$items, $in, $out] = $this->responses[$this->calls] ?? $this->responses[count($this->responses) - 1];
            $this->calls++;

            return new GeneratedCollectionDraft('T', 'd', $items, 'gpt-4o', $in, $out, '{"n":' . $this->calls . '}');
        }
    };
}

/** @return list<GeneratedItem> */
function items(string $prefix, int $count): array
{
    $out = [];
    for ($i = 1; $i <= $count; $i++) {
        $out[] = new GeneratedItem("{$prefix}{$i}", 'word', "перевод {$i}", null, 'B1');
    }

    return $out;
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
        ->and($collection?->ownerId()?->value)->toBe($this->user->value)
        ->and($request?->deliveredCount())->toBe(12) // asked 12, over-generated, trimmed back to 12
        // Image attachment is kicked off once, for the new collection, after success.
        ->and($this->attach->dispatched)->toBe([$request->collectionId()->value]);
});

it('tops up a shortfall and sums tokens and cost across both model calls', function () {
    $id = openGeneration($this); // requested 12

    // Primary under-delivers (10 usable), so exactly one top-up runs. The top-up returns fresh items.
    $generator = scriptedGenerator([
        [items('primary', 10), 700, 1200],
        [items('topup', 5), 300, 400],
    ]);
    (processWith($this, $generator))(new ProcessGeneration($id));

    $request = $this->requests->findById($id);
    // 10 primary + fresh top-up, trimmed to the requested 12.
    expect($request?->status())->toBe(GenerationStatus::Succeeded)
        ->and($generator->calls)->toBe(2)                 // one primary + one top-up, never a loop
        ->and($request?->deliveredCount())->toBe(12)
        // Spend is the SUM of both calls, not the second overwriting the first:
        ->and($request?->tokensIn())->toBe(1000)          // 700 + 300
        ->and($request?->tokensOut())->toBe(1600)         // 1200 + 400
        ->and($request?->costUsd())->toBe('0.018500');    // 0.013750 (call 1) + 0.004750 (call 2)
});

it('is an honest success when a top-up cannot close the gap', function () {
    $id = openGeneration($this); // requested 12

    // Primary yields 9; the top-up returns only items that duplicate the primary set, so nothing
    // new survives the merge. The result is 9 < 12 — recorded honestly, not a failure.
    $generator = scriptedGenerator([
        [items('primary', 9), 700, 1200],
        [items('primary', 3), 300, 400], // same texts as the primary set → deduped away
    ]);
    (processWith($this, $generator))(new ProcessGeneration($id));

    $request = $this->requests->findById($id);
    expect($request?->status())->toBe(GenerationStatus::Succeeded)
        ->and($generator->calls)->toBe(2)                 // still exactly one top-up
        ->and($request?->deliveredCount())->toBe(9)
        ->and($request?->collectionId())->not->toBeNull();

    $collection = $this->collections->findById($request->collectionId());
    expect($collection?->itemsCount())->toBe(9);
});

it('is idempotent — reprocessing a finished request creates nothing new', function () {
    $id = openGeneration($this);

    ($this->process)(new ProcessGeneration($id));
    ($this->process)(new ProcessGeneration($id));

    expect($this->collections->count())->toBe(1);
});

it('rejects a new request once the daily quota is exhausted', function () {
    // A free-tier user's daily allowance.
    expect(fn () => openGeneration($this, GenerationDailyLimit::FREE))
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
        cachedTermSet: new GetCollectionTermSetHandler($this->collections),
        attachImages: new RecordingImageAttachmentDispatcher(),
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

it('reuses a cached term set on an identical prompt without calling the model again', function () {
    // First generation populates the cache: a succeeded request plus its collection of terms.
    $first = openGeneration($this);
    ($this->process)(new ProcessGeneration($first));
    $termsAfterFirst = $this->terms->count();

    // A generator that blows up if the model is called at all — proves the cache path skips it.
    $throwing = new class implements CollectionGeneratorPort
    {
        public function generate(GenerationBrief $brief): GeneratedCollectionDraft
        {
            throw new RuntimeException('the model must not be called on a cache hit');
        }
    };

    $findOrCreate = new FindOrCreateTermHandler($this->terms, new TermNormalizer(), $this->clock);
    $process = new ProcessGenerationHandler(
        requests: $this->requests,
        generator: $throwing,
        validator: new DraftValidator(),
        importTerm: new ImportTermHandler($findOrCreate),
        createCollection: new CreateGeneratedCollectionHandler($this->collections, $this->clock),
        addTerm: new AddTermToCollectionHandler($this->collections),
        cachedTermSet: new GetCollectionTermSetHandler($this->collections),
        attachImages: new RecordingImageAttachmentDispatcher(),
        tx: new ImmediateTransactionManager(),
        clock: $this->clock,
    );

    // Second identical request (same prompt/langs/version) → cache hit.
    $second = openGeneration($this);
    ($process)(new ProcessGeneration($second));

    $request = $this->requests->findById($second);
    expect($request?->status())->toBe(GenerationStatus::Succeeded)
        ->and($request?->model())->toBe('cache')
        ->and($request?->costUsd())->toBe('0.000000')
        ->and($this->collections->count())->toBe(2)           // a fresh personal collection…
        ->and($this->terms->count())->toBe($termsAfterFirst); // …but no new terms (reused)
});

it('records a terminal failure via FailGeneration', function () {
    $id = openGeneration($this);

    (new FailGenerationHandler($this->requests, $this->clock))(new FailGeneration($id, 'boom'));

    expect($this->requests->findById($id)?->status())->toBe(GenerationStatus::Failed)
        ->and($this->requests->findById($id)?->error())->toBe('boom');
});
