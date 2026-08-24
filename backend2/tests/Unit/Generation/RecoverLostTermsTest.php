<?php

declare(strict_types=1);

use App\Modules\Collections\Application\Command\AddTermToCollectionHandler;
use App\Modules\Collections\Application\Query\GetCollectionTermSetHandler;
use App\Modules\Collections\Domain\Entity\Collection;
use App\Modules\Generation\Application\Command\RecoverLostTerms;
use App\Modules\Generation\Application\Command\RecoverLostTermsHandler;
use App\Modules\Generation\Application\Dto\RecoveredTermReport;
use App\Modules\Generation\Domain\Entity\GenerationRequest;
use App\Modules\Generation\Domain\ValueObject\GenerationStatus;
use App\Modules\Shared\Domain\ValueObject\CollectionId;
use App\Modules\Shared\Domain\ValueObject\GenerationRequestId;
use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Shared\Domain\ValueObject\UserId;
use App\Modules\Vocabulary\Application\Command\FindOrCreateTermHandler;
use App\Modules\Vocabulary\Application\Command\ImportTermHandler;
use App\Modules\Vocabulary\Domain\Service\TermNormalizer;
use Tests\Doubles\FakeTermLanguageReader;
use Tests\Doubles\FixedClock;
use Tests\Doubles\InMemoryCollectionRepository;
use Tests\Doubles\InMemoryGenerationRequestRepository;
use Tests\Doubles\InMemoryLoggedResponseReader;
use Tests\Doubles\InMemoryTermRepository;
use Tests\Doubles\RecordingEnrichmentDispatcher;
use Tests\Doubles\RecordingImageAttachmentDispatcher;

// The exact IDs RecoverLostTermsHandler::MANIFEST hardcodes — the manifest IS the thing under
// test, so the fixtures below must line up with it rather than with arbitrary generated ids.
const DOGFOOD_COLLECTION_ID = '01M08AP71D1KM0PFPYK3P71DV5';
const DOGFOOD_REQUEST_ID = '01M08ANAR4KBV7J69JFR4C32YV';
const PHARMACY_COLLECTION_ID = '01M00WHZEB4XHTWCHG5QYZGCMG';
const PHARMACY_REQUEST_ID = '01M00WHF71DHXW4PYSGSWPM4D0';
const PHARMACY_LOG_ID = '01M00WHZDN5B0QS66FASBVBC26';

function anItemJson(string $text): array
{
    return [
        'text' => $text,
        'type' => 'word',
        'transcription' => 'ˈtest',
        'translation' => 'перевод ' . $text,
        'example' => "Example with {$text}.",
        'example_translation' => 'Пример.',
        'cefr' => 'A2',
        'image_api_prompt' => 'a photo of ' . $text,
    ];
}

beforeEach(function () {
    $this->clock = new FixedClock(new DateTimeImmutable('2026-08-18T12:00:00Z'));
    $this->requests = new InMemoryGenerationRequestRepository();
    $this->collections = new InMemoryCollectionRepository();
    $this->logs = new InMemoryLoggedResponseReader();
    $this->terms = new InMemoryTermRepository();

    $findOrCreate = new FindOrCreateTermHandler($this->terms, new TermNormalizer(), $this->clock);
    $this->attach = new RecordingImageAttachmentDispatcher();
    $this->enrich = new RecordingEnrichmentDispatcher();
    $this->handler = new RecoverLostTermsHandler(
        requests: $this->requests,
        logs: $this->logs,
        termSet: new GetCollectionTermSetHandler($this->collections),
        importTerm: new ImportTermHandler($findOrCreate),
        addTerm: new AddTermToCollectionHandler($this->collections, new FakeTermLanguageReader()),
        attachImages: $this->attach,
        enrich: $this->enrich,
    );

    $owner = UserId::generate();

    // Dogfood: raw_response with 13 clean items; the collection already has the first 10.
    $dogfoodItems = array_map(static fn (int $i): array => anItemJson("dogword{$i}"), range(1, 10));
    $dogfoodItems[] = anItemJson('What brand do you recommend?');
    $dogfoodItems[] = anItemJson('to run out of');
    $dogfoodItems[] = anItemJson('Can you help me carry this?');
    $dogfoodRaw = json_encode(['title' => 'Buying Dog Food at the Store', 'description' => 'd', 'items' => $dogfoodItems]);

    $this->requests->save(GenerationRequest::reconstitute(
        id: GenerationRequestId::fromString(DOGFOOD_REQUEST_ID),
        userId: $owner,
        prompt: 'собака корм', normalizedPrompt: 'собака корм',
        sourceLang: new LanguageCode('ru'), targetLang: new LanguageCode('en'),
        levels: ['A2', 'B1'], size: 10, deliveredCount: 10, promptVersion: 'v8',
        status: GenerationStatus::Succeeded, model: 'gpt-4o', tokensIn: 100, tokensOut: 200, costUsd: '0.01',
        collectionId: CollectionId::fromString(DOGFOOD_COLLECTION_ID), error: null,
        rawResponse: $dogfoodRaw, createdAt: $this->clock->now(), finishedAt: $this->clock->now(),
    ));

    $dogfood = Collection::createGenerated(
        CollectionId::fromString(DOGFOOD_COLLECTION_ID), $owner, 'Buying Dog Food at the Store',
        new LanguageCode('ru'), new LanguageCode('en'), $this->clock->now(),
    );
    foreach (range(1, 10) as $i) {
        $dogfood->addTerm(TermId::generate(), new LanguageCode('en')); // pre-existing terms, unrelated to the 3 targets
    }
    $this->collections->save($dogfood);

    // Pharmacy: raw_response is unusable (no items key at all — mirrors "truncated before the
    // items key" in spirit); the manifest routes this one through the LOG instead.
    $this->requests->save(GenerationRequest::reconstitute(
        id: GenerationRequestId::fromString(PHARMACY_REQUEST_ID),
        userId: $owner,
        prompt: 'аптека', normalizedPrompt: 'аптека',
        sourceLang: new LanguageCode('ru'), targetLang: new LanguageCode('en'),
        levels: ['A2', 'B1'], size: 15, deliveredCount: 15, promptVersion: 'v8',
        status: GenerationStatus::Succeeded, model: 'gpt-4o', tokensIn: 100, tokensOut: 200, costUsd: '0.01',
        collectionId: CollectionId::fromString(PHARMACY_COLLECTION_ID), error: null,
        rawResponse: '{"title":"broken', createdAt: $this->clock->now(), finishedAt: $this->clock->now(),
    ));

    $pharmacyItems = array_map(static fn (int $i): array => anItemJson("pharmword{$i}"), range(1, 15));
    $pharmacyItems[] = anItemJson('take with water');
    $pharmacyItems[] = anItemJson('pharmacy');
    $pharmacyItems[] = anItemJson('tablet');
    $pharmacyItems[] = anItemJson('pharmacist');
    $pharmacyContent = json_encode(['title' => 'Going to the Pharmacy: Pain Relief', 'description' => 'd', 'items' => $pharmacyItems]);
    $this->logs->put(PHARMACY_LOG_ID, ['choices' => [['message' => ['content' => $pharmacyContent]]]]);

    $pharmacy = Collection::createGenerated(
        CollectionId::fromString(PHARMACY_COLLECTION_ID), $owner, 'Going to the Pharmacy: Pain Relief',
        new LanguageCode('ru'), new LanguageCode('en'), $this->clock->now(),
    );
    foreach (range(1, 15) as $i) {
        $pharmacy->addTerm(TermId::generate(), new LanguageCode('en'));
    }
    $this->collections->save($pharmacy);
});

it('dry run reports every target text as planned and writes nothing', function () {
    $reports = ($this->handler)(new RecoverLostTerms(apply: false));

    expect($reports)->toHaveCount(7) // 3 dogfood + 4 pharmacy
        ->and(array_unique(array_map(fn (RecoveredTermReport $r) => $r->status, $reports)))->toBe(['planned'])
        ->and($this->terms->count())->toBe(0);
});

it('recovers the dogfood items from raw_response and the pharmacy items from the log', function () {
    $reports = ($this->handler)(new RecoverLostTerms(apply: true));

    $byText = [];
    foreach ($reports as $r) {
        $byText[$r->text] = $r;
    }

    expect($byText['What brand do you recommend?']->status)->toBe('recovered')
        ->and($byText['to run out of']->status)->toBe('recovered')
        ->and($byText['Can you help me carry this?']->status)->toBe('recovered')
        ->and($byText['take with water']->status)->toBe('recovered')
        ->and($byText['pharmacy']->status)->toBe('recovered')
        ->and($byText['tablet']->status)->toBe('recovered')
        ->and($byText['pharmacist']->status)->toBe('recovered');

    $dogfood = $this->collections->findById(CollectionId::fromString(DOGFOOD_COLLECTION_ID));
    $pharmacy = $this->collections->findById(CollectionId::fromString(PHARMACY_COLLECTION_ID));
    expect($dogfood?->itemsCount())->toBe(13)
        ->and($pharmacy?->itemsCount())->toBe(19);

    // Mirrors the standard generation path: a recovered collection gets the same free image
    // search + enrichment станок follow-up a freshly generated one would.
    expect($this->attach->dispatched)->toBe([DOGFOOD_COLLECTION_ID, PHARMACY_COLLECTION_ID])
        ->and(array_column($this->enrich->collections, 'collection_id'))->toBe([DOGFOOD_COLLECTION_ID, PHARMACY_COLLECTION_ID]);
});

it('is idempotent — a second --apply run recovers nothing new', function () {
    ($this->handler)(new RecoverLostTerms(apply: true));
    $second = ($this->handler)(new RecoverLostTerms(apply: true));

    expect(array_unique(array_map(fn (RecoveredTermReport $r) => $r->status, $second)))->toBe(['already_present']);

    $dogfood = $this->collections->findById(CollectionId::fromString(DOGFOOD_COLLECTION_ID));
    $pharmacy = $this->collections->findById(CollectionId::fromString(PHARMACY_COLLECTION_ID));
    expect($dogfood?->itemsCount())->toBe(13)
        ->and($pharmacy?->itemsCount())->toBe(19)
        ->and($this->terms->count())->toBe(7); // no duplicate Term rows either

    // Second run recovered nothing, so it must not re-dispatch image search / enrichment spend —
    // only the first run's two dispatches should be recorded.
    expect($this->attach->dispatched)->toBe([DOGFOOD_COLLECTION_ID, PHARMACY_COLLECTION_ID])
        ->and($this->enrich->collections)->toHaveCount(2);
});
