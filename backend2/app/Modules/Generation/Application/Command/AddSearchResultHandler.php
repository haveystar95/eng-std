<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Command;

use App\Modules\Collections\Application\Command\AddTermToCollection;
use App\Modules\Collections\Application\Command\AddTermToCollectionHandler;
use App\Modules\Collections\Application\Command\EnsureDefaultCollection;
use App\Modules\Collections\Application\Command\EnsureDefaultCollectionHandler;
use App\Modules\Collections\Application\Port\TermFolderMembershipReader;
use App\Modules\Generation\Application\Dto\CachedLookup;
use App\Modules\Generation\Application\Dto\SavedSearchResult;
use App\Modules\Generation\Application\Port\DispatchesEnrichment;
use App\Modules\Generation\Application\Port\SearchLookupCache;
use App\Modules\Generation\Application\Service\LearnerLanguages;
use App\Modules\Generation\Domain\Exception\LookupNotFound;
use App\Modules\Learning\Application\Command\EnrollTerm;
use App\Modules\Learning\Application\Command\EnrollTermHandler;
use App\Modules\Shared\Domain\Service\TransactionManager;
use App\Modules\Shared\Domain\ValueObject\CollectionId;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Vocabulary\Application\Command\ImportTerm;
use App\Modules\Vocabulary\Application\Command\ImportTermHandler;
use App\Modules\Vocabulary\Application\Dto\ExampleInput;
use App\Modules\Vocabulary\Application\Dto\TranslationInput;
use App\Modules\Vocabulary\Application\Port\TermDescriptionWriter;

/**
 * The tap that saves a word: term → folder → POOL.
 *
 * The third step is the one worth being explicit about. Enrolling here does not break the pool rule
 * — «слово учится, только когда зачислено осознанным актом» — it OBEYS it: saving a word you went
 * looking for, typed out and read a card about is as deliberate as an act gets, and it is the same
 * door «Учить это слово» opens ({@see EnrollTerm}). What the rule forbids is enrolling by side
 * effect (adding a collection, generating one, answering a practice card), and none of those is
 * what happened here.
 *
 * IDEMPOTENT throughout, because the button is one tap on a phone with a flaky connection: the term
 * dedups on its normalized text, adding it to a folder it is already in is a no-op, the description
 * is write-once per (term, language), and enrolling an enrolled pair does not touch its rung, its
 * counter or its due date.
 */
final readonly class AddSearchResultHandler
{
    public function __construct(
        private SearchLookupCache $cache,
        private ImportTermHandler $importTerm,
        private TermDescriptionWriter $descriptions,
        private AddTermToCollectionHandler $addTerm,
        private EnsureDefaultCollectionHandler $ensureDefault,
        private TermFolderMembershipReader $folders,
        private EnrollTermHandler $enroll,
        private DispatchesEnrichment $enrichment,
        private LearnerLanguages $languages,
        private TransactionManager $tx,
        /** `GENERATION_AUTO_ENRICH` — the same switch that governs the post-generation chain. */
        private bool $autoEnrich = true,
    ) {}

    public function __invoke(AddSearchResult $command): SavedSearchResult
    {
        $langs = $this->languages->forUser($command->actorId);
        $lookup = $command->lookupId !== null
            ? ($this->cache->findById($command->lookupId) ?? throw LookupNotFound::withId($command->lookupId))
            : null;

        [$termId, $collectionId, $added, $enrolled] = $this->tx->run(
            function () use ($command, $lookup, $langs): array {
                $termId = $lookup !== null
                    ? $this->termFromLookup($lookup, $langs->native->value)
                    : ($command->termId ?? throw LookupNotFound::nothingToAdd());

                // Ownership is asserted by the add itself, so an unowned folder never reaches the
                // enrolment below — a save into someone else's shelf must not enrol anything.
                $collectionId = $command->collectionId
                    ?? ($this->ensureDefault)(new EnsureDefaultCollection(
                        ownerId: $command->actorId,
                        sourceLang: $langs->native,
                        targetLang: $langs->target,
                    ));

                $before = $this->folders->foldersHolding($command->actorId, [$termId->value]);
                $wasThere = $this->holds($before[$termId->value] ?? [], $collectionId);

                ($this->addTerm)(new AddTermToCollection(
                    collectionId: $collectionId,
                    termId: $termId,
                    actorId: $command->actorId,
                ));

                $enrolled = ($this->enroll)(new EnrollTerm($command->actorId, $termId));

                return [$termId, $collectionId, ! $wasThere, $enrolled];
            },
        );

        // Outside the transaction: the станок is a queue job, and a job dispatched inside a
        // transaction that then rolls back is a worker looking for a term that does not exist.
        if ($this->autoEnrich && $added) {
            $this->enrichment->enrichTerms(
                [$termId->value],
                BuildTermEnrichmentsHandler::VERSION,
                $langs->native->value,
            );
        }

        $folder = $this->folderOf($command, $termId, $collectionId);

        return new SavedSearchResult(
            termId: $termId->value,
            collectionId: $collectionId->value,
            collectionTitle: $folder['title'],
            collectionIsDefault: $folder['is_default'],
            added: $added,
            enrolled: $enrolled,
        );
    }

    /**
     * Turn a cached answer into a term. `ImportTerm` deduplicates on the normalized text, so a word
     * two learners looked up separately — or one looked up twice — is ONE term, and the second save
     * merely adds it to another folder.
     */
    private function termFromLookup(CachedLookup $lookup, string $nativeLang): TermId
    {
        $termId = ($this->importTerm)(new ImportTerm(
            lang: new \App\Modules\Shared\Domain\ValueObject\LanguageCode($lookup->lang),
            text: $lookup->text,
            type: $lookup->type,
            pos: null,
            // `user` and not `ai`: the model wrote the words, but the learner chose the word. The
            // catalogue's provenance columns below record which model, which prompt.
            source: 'user',
            translations: [new TranslationInput(
                new \App\Modules\Shared\Domain\ValueObject\LanguageCode($nativeLang),
                $lookup->translation,
                isPrimary: true,
            )],
            ipa: $lookup->transcription,
            examples: $lookup->example !== null
                ? [new ExampleInput($lookup->example, $lookup->exampleTranslation)]
                : [],
            cefr: $lookup->cefr,
            promptVersion: $lookup->promptVersion,
            generationModel: $lookup->model,
        ));

        // The description is written in the language BEING LEARNED — it is the question the
        // `description_match` trainer asks, not a gloss for the learner's own language.
        $this->descriptions->ensure(
            $termId,
            $lookup->lang,
            $lookup->description,
            source: 'ai',
            promptVersion: $lookup->promptVersion,
            generationModel: $lookup->model,
        );

        return $termId;
    }

    /** @param list<array{id: string, title: string, is_default: bool}> $folders */
    private function holds(array $folders, CollectionId $collectionId): bool
    {
        foreach ($folders as $folder) {
            if ($folder['id'] === $collectionId->value) {
                return true;
            }
        }

        return false;
    }

    /** @return array{title: string, is_default: bool} */
    private function folderOf(AddSearchResult $command, TermId $termId, CollectionId $collectionId): array
    {
        $folders = $this->folders->foldersHolding($command->actorId, [$termId->value])[$termId->value] ?? [];
        foreach ($folders as $folder) {
            if ($folder['id'] === $collectionId->value) {
                return ['title' => $folder['title'], 'is_default' => $folder['is_default']];
            }
        }

        // Unreachable while the add above succeeded; a blank label beats a fabricated one.
        return ['title' => '', 'is_default' => false];
    }
}
