<?php

declare(strict_types=1);

use App\Modules\Collections\Application\Command\AddTermToCollectionHandler;
use App\Modules\Collections\Application\Command\CreateGeneratedCollectionHandler;
use App\Modules\Collections\Application\Query\GetCollectionTermSetHandler;
use App\Modules\Generation\Application\Command\FailGeneration;
use App\Modules\Generation\Application\Command\FailGenerationHandler;
use App\Modules\Generation\Application\Command\BuildTermEnrichmentsHandler;
use App\Modules\Generation\Application\Command\ProcessGeneration;
use App\Modules\Generation\Application\Command\ProcessGenerationHandler;
use App\Modules\Generation\Application\Command\RequestCollectionGeneration;
use App\Modules\Generation\Application\Command\RequestCollectionGenerationHandler;
use App\Modules\Generation\Application\Dto\GeneratedCollectionDraft;
use App\Modules\Generation\Application\Dto\GeneratedItem;
use App\Modules\Generation\Application\Dto\GenerationBrief;
use App\Modules\Generation\Application\Port\CollectionGeneratorPort;
use App\Modules\Generation\Application\Service\DraftValidator;
use App\Modules\Generation\Application\Service\LanguageBarrier;
use App\Modules\Generation\Domain\Exception\GenerationQuotaExceeded;
use App\Modules\Generation\Domain\Exception\InvalidGeneratedDraft;
use App\Modules\Generation\Domain\Service\GenerationDailyLimit;
use App\Modules\Generation\Domain\Service\PromptNormalizer;
use App\Modules\Generation\Domain\ValueObject\GenerationStatus;
use App\Modules\Generation\Infrastructure\Adapter\FakeCollectionGenerator;
use App\Modules\Generation\Infrastructure\Adapter\FakeTranslationRepairer;
use App\Modules\Shared\Domain\ValueObject\GenerationRequestId;
use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use App\Modules\Shared\Domain\ValueObject\UserId;
use App\Modules\Vocabulary\Application\Command\FindOrCreateTermHandler;
use App\Modules\Vocabulary\Application\Command\ImportTermHandler;
use App\Modules\Vocabulary\Domain\Service\TermNormalizer;
use Tests\Doubles\FakeDefaultTargetLangReader;
use Tests\Doubles\FakeGenerationQuota;
use Tests\Doubles\FakeUserTierReader;
use Tests\Doubles\FixedClock;
use Tests\Doubles\ImmediateTransactionManager;
use Tests\Doubles\InMemoryCollectionRepository;
use Tests\Doubles\InMemoryGenerationRequestRepository;
use Tests\Doubles\InMemoryTermRepository;
use Tests\Doubles\RecordingEnrichmentDispatcher;
use Tests\Doubles\RecordingExampleRepairDispatcher;
use Tests\Doubles\RecordingImageAttachmentDispatcher;
use Tests\Doubles\RecordingRejectionJournal;
use Tests\Doubles\ScriptedTranslationRepairer;

beforeEach(function () {
    $this->clock = new FixedClock(new DateTimeImmutable('2026-07-27T12:00:00Z'));
    $this->requests = new InMemoryGenerationRequestRepository();
    $this->terms = new InMemoryTermRepository();
    $this->collections = new InMemoryCollectionRepository();
    $this->attach = new RecordingImageAttachmentDispatcher();
    $this->enrich = new RecordingEnrichmentDispatcher();
    $this->repairExamples = new RecordingExampleRepairDispatcher();
    $this->user = UserId::generate();

    $findOrCreate = new FindOrCreateTermHandler($this->terms, new TermNormalizer(), $this->clock);
    $this->process = new ProcessGenerationHandler(
        requests: $this->requests,
        generator: new FakeCollectionGenerator(),
        validator: new DraftValidator(),
        barrier: new LanguageBarrier(new FakeTranslationRepairer()),
        rejections: new RecordingRejectionJournal(),
        importTerm: new ImportTermHandler($findOrCreate),
        createCollection: new CreateGeneratedCollectionHandler($this->collections, $this->clock),
        addTerm: new AddTermToCollectionHandler($this->collections),
        cachedTermSet: new GetCollectionTermSetHandler($this->collections),
        attachImages: $this->attach,
        enrich: $this->enrich,
        repairExamples: $this->repairExamples,
        tx: new ImmediateTransactionManager(),
        clock: $this->clock,
    );
});

function openGeneration(object $ctx, int $used = 0): GenerationRequestId
{
    $handler = new RequestCollectionGenerationHandler(
        $ctx->requests, new FakeGenerationQuota($used), new PromptNormalizer(), $ctx->clock,
        new FakeUserTierReader(), new GenerationDailyLimit(), new FakeDefaultTargetLangReader(),
    );

    return $handler(new RequestCollectionGeneration(
        $ctx->user, 'иду в банк', new LanguageCode('ru'), new LanguageCode('en'), ['A2', 'B1'], 12,
    ))->id;
}

/** Build a ProcessGenerationHandler wired to a specific generator (the rest are shared fakes). */
function processWith(object $ctx, CollectionGeneratorPort $generator): ProcessGenerationHandler
{
    $findOrCreate = new FindOrCreateTermHandler($ctx->terms, new TermNormalizer(), $ctx->clock);

    return new ProcessGenerationHandler(
        requests: $ctx->requests,
        generator: $generator,
        validator: new DraftValidator(),
        barrier: new LanguageBarrier(new FakeTranslationRepairer()),
        rejections: new RecordingRejectionJournal(),
        importTerm: new ImportTermHandler($findOrCreate),
        createCollection: new CreateGeneratedCollectionHandler($ctx->collections, $ctx->clock),
        addTerm: new AddTermToCollectionHandler($ctx->collections),
        cachedTermSet: new GetCollectionTermSetHandler($ctx->collections),
        attachImages: new RecordingImageAttachmentDispatcher(),
        enrich: new RecordingEnrichmentDispatcher(),
        repairExamples: new RecordingExampleRepairDispatcher(),
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
        ->and($this->terms->count())->toBe(16)
        ->and($this->collections->count())->toBe(1);

    $collection = $this->collections->findById($request->collectionId());
    expect($collection?->itemsCount())->toBe(16)
        ->and($collection?->ownerId()?->value)->toBe($this->user->value)
        // Asked 12; overshoot generates 16 (ceil(12*1.3)); size is approximate, so all 16 valid
        // items ship — no trim back toward the requested count (owner decision, 2026-08-18).
        ->and($request?->deliveredCount())->toBe(16)
        // Image attachment is kicked off once, for the new collection, after success.
        ->and($this->attach->dispatched)->toBe([$request->collectionId()->value])
        // …and the second follow-up, same shape and same reason: give a real example to whatever the
        // validator refused for merely repeating its term (QA-7), and only THEN build the exercise
        // machinery on top of those examples. One call, because the order is the point — the станок
        // used to be dispatched first and reached echo terms before their example existed (audit A2).
        ->and($this->repairExamples->collections)->toBe([[
            'collection_id' => $request->collectionId()->value,
            'owner_id' => $this->user->value,
            'generator_version' => BuildTermEnrichmentsHandler::VERSION,
        ]])
        // Nothing enriches a fresh collection on its own any more: that path belongs to the chain
        // above. The direct dispatcher is only the cache-hit path's, where examples are settled.
        ->and($this->enrich->collections)->toBe([]);
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
    // 10 primary + fresh top-up (5), all 15 ship — size is approximate, no trim back to 12.
    expect($request?->status())->toBe(GenerationStatus::Succeeded)
        ->and($generator->calls)->toBe(2)                 // one primary + one top-up, never a loop
        ->and($request?->deliveredCount())->toBe(15)
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
        barrier: new LanguageBarrier(new FakeTranslationRepairer()),
        rejections: new RecordingRejectionJournal(),
        importTerm: new ImportTermHandler($findOrCreate),
        createCollection: new CreateGeneratedCollectionHandler($this->collections, $this->clock),
        addTerm: new AddTermToCollectionHandler($this->collections),
        cachedTermSet: new GetCollectionTermSetHandler($this->collections),
        attachImages: new RecordingImageAttachmentDispatcher(),
        enrich: new RecordingEnrichmentDispatcher(),
        repairExamples: new RecordingExampleRepairDispatcher(),
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
        barrier: new LanguageBarrier(new FakeTranslationRepairer()),
        rejections: new RecordingRejectionJournal(),
        importTerm: new ImportTermHandler($findOrCreate),
        createCollection: new CreateGeneratedCollectionHandler($this->collections, $this->clock),
        addTerm: new AddTermToCollectionHandler($this->collections),
        cachedTermSet: new GetCollectionTermSetHandler($this->collections),
        attachImages: new RecordingImageAttachmentDispatcher(),
        enrich: new RecordingEnrichmentDispatcher(),
        repairExamples: new RecordingExampleRepairDispatcher(),
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

/** A batch of clean items, with one field of one item optionally poisoned. */
function batch(int $count, ?string $poisonedTranslation = null, int $offset = 0): array
{
    $items = [];
    for ($n = 1; $n <= $count; $n++) {
        $i = $offset + $n;
        $items[] = new GeneratedItem(
            text: "term {$i}",
            type: 'phrase',
            translation: $n === 1 && $poisonedTranslation !== null ? $poisonedTranslation : "перевод {$i}",
            example: "This is sentence {$i}.",
            cefr: 'B1',
            transcription: "ipa {$i}",
            exampleTranslation: "Это предложение {$i}.",
        );
    }

    return $items;
}

/** Build a handler with an explicit barrier + journal, so a test can script the repairer. */
function processWithBarrier(object $ctx, CollectionGeneratorPort $generator, LanguageBarrier $barrier, RecordingRejectionJournal $journal): ProcessGenerationHandler
{
    $findOrCreate = new FindOrCreateTermHandler($ctx->terms, new TermNormalizer(), $ctx->clock);

    return new ProcessGenerationHandler(
        requests: $ctx->requests,
        generator: $generator,
        validator: new DraftValidator(),
        barrier: $barrier,
        rejections: $journal,
        importTerm: new ImportTermHandler($findOrCreate),
        createCollection: new CreateGeneratedCollectionHandler($ctx->collections, $ctx->clock),
        addTerm: new AddTermToCollectionHandler($ctx->collections),
        cachedTermSet: new GetCollectionTermSetHandler($ctx->collections),
        attachImages: new RecordingImageAttachmentDispatcher(),
        enrich: new RecordingEnrichmentDispatcher(),
        repairExamples: new RecordingExampleRepairDispatcher(),
        tx: new ImmediateTransactionManager(),
        clock: $ctx->clock,
    );
}

/**
 * Outcome one, end to end: a Ukrainian translation never reaches the database, because the retry
 * fixed it. The term IS written — with the repaired Russian, not the model's first answer.
 */
it('writes the repaired translation when the barrier fixes a tainted item', function () {
    $journal = new RecordingRejectionJournal();
    $repairer = new ScriptedTranslationRepairer(['держаться на одной волне']);
    $generator = scriptedGenerator([[batch(12, 'на одній хвилі'), 900, 1500]]);

    $id = openGeneration($this);
    (processWithBarrier($this, $generator, new LanguageBarrier($repairer), $journal))(new ProcessGeneration($id));

    expect($repairer->calls)->toBe(1)
        ->and($journal->all())->toBe([])
        ->and($this->requests->findById($id)?->deliveredCount())->toBe(12);

    $written = $this->terms->all();
    $translations = [];
    foreach ($written as $term) {
        foreach ($term->translations() as $translation) {
            $translations[] = $translation->text;
        }
    }
    expect($translations)->toContain('держаться на одной волне')
        ->and($translations)->not->toContain('на одній хвилі');
});

/**
 * Outcome two, end to end: the retries fail, the term is NOT written, the hole is filled by the
 * top-up, and the drop leaves a row saying which item and why. The collection is full-size, so
 * without that row nothing about the run would look unusual — which is the failure mode this
 * whole change exists to remove.
 */
it('drops an unfixable item, tops up the hole, and records the rejection', function () {
    $journal = new RecordingRejectionJournal();
    $repairer = new ScriptedTranslationRepairer(['на одній хвилі', 'розуміти одне одного']);
    $generator = scriptedGenerator([
        [batch(12, 'на одній хвилі'), 900, 1500],
        [batch(2, null, 100), 200, 300],   // the top-up, clean
    ]);

    $id = openGeneration($this);
    (processWithBarrier($this, $generator, new LanguageBarrier($repairer), $journal))(new ProcessGeneration($id));

    expect($repairer->calls)->toBe(LanguageBarrier::MAX_ATTEMPTS)
        ->and($generator->calls)->toBe(2)                       // the hole triggered a top-up
        // 11 primary survivors (1 dropped, unfixable) + 2 fresh top-up = 13, all ship uncapped.
        ->and($this->requests->findById($id)?->deliveredCount())->toBe(13);

    $rejections = $journal->all();
    expect($rejections)->toHaveCount(1)
        ->and($rejections[0]->text)->toBe('term 1')
        ->and($rejections[0]->field)->toBe('translation')
        ->and($rejections[0]->attempts)->toBe(LanguageBarrier::MAX_ATTEMPTS);

    $translations = [];
    foreach ($this->terms->all() as $term) {
        foreach ($term->translations() as $translation) {
            $translations[] = $translation->text;
        }
    }
    expect($translations)->not->toContain('на одній хвилі');
});
