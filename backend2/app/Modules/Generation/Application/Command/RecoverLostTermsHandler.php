<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Command;

use App\Modules\Collections\Application\Command\AddTermToCollection;
use App\Modules\Collections\Application\Command\AddTermToCollectionHandler;
use App\Modules\Collections\Application\Query\GetCollectionTermSet;
use App\Modules\Collections\Application\Query\GetCollectionTermSetHandler;
use App\Modules\Generation\Application\Command\BuildTermEnrichmentsHandler;
use App\Modules\Generation\Application\Dto\RecoveredTermReport;
use App\Modules\Generation\Application\Port\DispatchesEnrichment;
use App\Modules\Generation\Application\Port\DispatchesImageAttachment;
use App\Modules\Generation\Application\Port\LoggedResponseReader;
use App\Modules\Generation\Domain\Repository\GenerationRequestRepository;
use App\Modules\Generation\Domain\Service\RawItemsExtractor;
use App\Modules\Shared\Domain\ValueObject\CollectionId;
use App\Modules\Shared\Domain\ValueObject\GenerationRequestId;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Vocabulary\Application\Command\ImportTerm;
use App\Modules\Vocabulary\Application\Command\ImportTermHandler;
use App\Modules\Vocabulary\Application\Dto\ExampleInput;
use App\Modules\Vocabulary\Application\Dto\TranslationInput;
use Throwable;

/**
 * One-off, idempotent recovery for three collections that lost terms to the positional-trim bug
 * fixed alongside this (QA, 2026-08-18) — `DraftValidator`/`GenerationPipeline` used to
 * `array_slice` an already-valid, over-generated batch down to the requested size, silently
 * discarding whichever items landed late. This re-derives the discarded items from the exact
 * model response that produced them (never re-generates), through the normal import path, so
 * they get the same translation/example/transcription/image-query the model actually gave them.
 *
 * The manifest below is deliberately hardcoded, not auto-discovered: each entry was found by
 * hand — locate the collection, read `generation_requests.raw_response` (truncated to 4000 chars
 * at write time) or, when that string was cut off before reaching the missing items, the fuller
 * `api_request_logs.response_body` for the same call's `purpose='generation'` row — and confirm
 * the target texts are both present in the source and absent from the collection today. A
 * search-by-heuristic version of this would be wrong the moment two collections' timings
 * overlapped; a fixed manifest is auditable instead.
 *
 * Idempotent by construction, not by a pre-check: {@see ImportTermHandler} dedups globally by
 * (lang, normalized_text, pos), and {@see \App\Modules\Collections\Domain\Entity\Collection::addTerm()}
 * is a no-op if the term is already in the collection — so re-running this with `--apply` a
 * second time imports nothing new. The `already_present` vs `recovered` split in the report is
 * for visibility only, from a term-set snapshot taken before this run's own writes.
 */
final readonly class RecoverLostTermsHandler
{
    /**
     * "Job Interview Preparation" (01M02AQ230J58K8HV26EDK1MFN) is deliberately NOT in this
     * manifest: its `generation_requests.raw_response` is truncated mid-string before any
     * items are lost, and its `api_request_logs` `purpose='generation'` row is itself a
     * `{"_truncated":true}` placeholder (the response exceeded the log's own size cap) — both
     * sources are exhausted, so there is nothing to recover from and nothing to search for.
     * Reported as unrecoverable in the naряд report, not modeled here as a manifest entry with
     * no targets.
     *
     * @var list<array{
     *     collectionId: string,
     *     generationRequestId: string,
     *     source: 'raw_response'|'log',
     *     logId: string|null,
     *     targets: list<string>,
     * }>
     */
    private const MANIFEST = [
        [
            'collectionId' => '01M08AP71D1KM0PFPYK3P71DV5', // Buying Dog Food at the Store
            'generationRequestId' => '01M08ANAR4KBV7J69JFR4C32YV',
            'source' => 'raw_response', // untruncated: the full items array closed cleanly
            'logId' => null,
            'targets' => [
                'What brand do you recommend?',
                'to run out of',
                'Can you help me carry this?',
            ],
        ],
        [
            'collectionId' => '01M00WHZEB4XHTWCHG5QYZGCMG', // Going to the Pharmacy: Pain Relief
            'generationRequestId' => '01M00WHF71DHXW4PYSGSWPM4D0',
            // raw_response is truncated mid-item (item 14 of 19); the targets are items 16-19,
            // so only the fuller logged response reaches far enough to contain them.
            'source' => 'log',
            'logId' => '01M00WHZDN5B0QS66FASBVBC26',
            'targets' => [
                'take with water',
                'pharmacy',
                'tablet',
                'pharmacist',
            ],
        ],
    ];

    public function __construct(
        private GenerationRequestRepository $requests,
        private LoggedResponseReader $logs,
        private GetCollectionTermSetHandler $termSet,
        private ImportTermHandler $importTerm,
        private AddTermToCollectionHandler $addTerm,
        private DispatchesImageAttachment $attachImages,
        private DispatchesEnrichment $enrich,
    ) {}

    /** @return list<RecoveredTermReport> */
    public function __invoke(RecoverLostTerms $command): array
    {
        $reports = [];

        foreach (self::MANIFEST as $entry) {
            $collectionId = CollectionId::fromString($entry['collectionId']);
            $request = $this->requests->findById(GenerationRequestId::fromString($entry['generationRequestId']));
            if ($request === null) {
                foreach ($entry['targets'] as $text) {
                    $reports[] = new RecoveredTermReport(
                        collectionTitle: $entry['collectionId'],
                        collectionId: $entry['collectionId'],
                        text: $text,
                        status: 'unrecoverable',
                        reason: 'generation request not found',
                    );
                }

                continue;
            }

            try {
                $extracted = $entry['source'] === 'raw_response'
                    ? RawItemsExtractor::extract($request->rawResponse() ?? '')
                    : RawItemsExtractor::extractFromLoggedResponse($this->logs->findResponseBody($entry['logId']) ?? []);
            } catch (Throwable $e) {
                foreach ($entry['targets'] as $text) {
                    $reports[] = new RecoveredTermReport(
                        collectionTitle: $entry['collectionId'],
                        collectionId: $entry['collectionId'],
                        text: $text,
                        status: 'unrecoverable',
                        reason: $e->getMessage(),
                    );
                }

                continue;
            }

            $byText = [];
            foreach ($extracted['items'] as $item) {
                if (is_string($item['text'] ?? null)) {
                    $byText[$item['text']] = $item;
                }
            }

            // Read-only, so safe in a dry run too — the title is for the report, the term-id
            // snapshot (taken BEFORE this run's own writes) is what tells "already there" from
            // "recovered just now" when applying.
            $collectionView = ($this->termSet)(new GetCollectionTermSet($collectionId));
            if ($collectionView === null) {
                foreach ($entry['targets'] as $text) {
                    $reports[] = new RecoveredTermReport(
                        collectionTitle: $entry['collectionId'],
                        collectionId: $entry['collectionId'],
                        text: $text,
                        status: 'unrecoverable',
                        reason: 'collection not found',
                    );
                }

                continue;
            }
            $title = $collectionView->title;
            $before = $collectionView->termIds;

            foreach ($entry['targets'] as $text) {
                $item = $byText[$text] ?? null;
                if ($item === null) {
                    $reports[] = new RecoveredTermReport(
                        collectionTitle: $title,
                        collectionId: $entry['collectionId'],
                        text: $text,
                        status: 'unrecoverable',
                        reason: 'not found in the extracted source (truncated before reaching it?)',
                    );

                    continue;
                }

                if (! $command->apply) {
                    $reports[] = new RecoveredTermReport(
                        collectionTitle: $title,
                        collectionId: $entry['collectionId'],
                        text: $text,
                        status: 'planned',
                    );

                    continue;
                }

                $termId = ($this->importTerm)(new ImportTerm(
                    lang: $request->targetLang(),
                    text: $item['text'],
                    type: is_string($item['type'] ?? null) ? $item['type'] : 'word',
                    pos: null,
                    source: 'ai',
                    translations: is_string($item['translation'] ?? null)
                        ? [new TranslationInput($request->sourceLang(), $item['translation'], isPrimary: true)]
                        : [],
                    ipa: is_string($item['transcription'] ?? null) ? $item['transcription'] : null,
                    examples: is_string($item['example'] ?? null)
                        // Recovered from the request's own raw answer, so the pair is the request's:
                        // the gloss is in its SOURCE language, like the translation above.
                        ? [new ExampleInput(
                            $item['example'],
                            is_string($item['example_translation'] ?? null) ? $item['example_translation'] : null,
                            $request->sourceLang(),
                        )]
                        : [],
                    cefr: is_string($item['cefr'] ?? null) ? $item['cefr'] : null,
                    imageApiPrompt: is_string($item['image_api_prompt'] ?? null) ? $item['image_api_prompt'] : null,
                ));

                $wasAlreadyPresent = in_array($termId->value, $before, true);

                ($this->addTerm)(new AddTermToCollection($collectionId, $termId, $request->userId()));

                $reports[] = new RecoveredTermReport(
                    collectionTitle: $title,
                    collectionId: $entry['collectionId'],
                    text: $text,
                    status: $wasAlreadyPresent ? 'already_present' : 'recovered',
                    termId: $termId->value,
                );
            }

            // Mirror the standard generation path for anything genuinely new this run: a free
            // image search for the recovered terms' image_api_prompt, and the enrichment станок
            // (accepted variants) — both best-effort and both skipped on a re-run where nothing
            // in this collection was actually recovered, so a repeat --apply doesn't re-dispatch.
            $anyRecovered = array_filter(
                $reports,
                static fn (RecoveredTermReport $r): bool => $r->collectionId === $entry['collectionId'] && $r->status === 'recovered',
            );
            if ($anyRecovered !== []) {
                $this->attachImages->dispatch($collectionId);
                $this->enrich->enrichCollection($collectionId->value, BuildTermEnrichmentsHandler::VERSION);
            }
        }

        return $reports;
    }
}
