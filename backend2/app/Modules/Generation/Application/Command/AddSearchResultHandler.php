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
use App\Modules\Generation\Application\Port\DispatchesImageAttachment;
use App\Modules\Generation\Application\Port\SearchLookupCache;
use App\Modules\Generation\Application\Service\LearnerLanguages;
use App\Modules\Generation\Domain\Exception\LookupNotFound;
use App\Modules\Generation\Domain\Service\EnrichmentValidator;
use App\Modules\Learning\Application\Command\EnrollTerm;
use App\Modules\Learning\Application\Command\EnrollTermHandler;
use App\Modules\Shared\Domain\Service\TransactionManager;
use App\Modules\Shared\Domain\ValueObject\CollectionId;
use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Vocabulary\Application\Command\ImportTerm;
use App\Modules\Vocabulary\Application\Command\ImportTermEnrichment;
use App\Modules\Vocabulary\Application\Command\ImportTermEnrichmentHandler;
use App\Modules\Vocabulary\Application\Command\ImportTermHandler;
use App\Modules\Vocabulary\Application\Command\PinTermTranslation;
use App\Modules\Vocabulary\Application\Command\PinTermTranslationHandler;
use App\Modules\Vocabulary\Application\Dto\ExampleInput;
use App\Modules\Vocabulary\Application\Dto\TermSynonymInput;
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
        private ImportTermEnrichmentHandler $importEnrichment,
        private PinTermTranslationHandler $pinTranslation,
        private TermDescriptionWriter $descriptions,
        private AddTermToCollectionHandler $addTerm,
        private EnsureDefaultCollectionHandler $ensureDefault,
        private TermFolderMembershipReader $folders,
        private EnrollTermHandler $enroll,
        private DispatchesEnrichment $enrichment,
        private DispatchesImageAttachment $imageAttachment,
        private LearnerLanguages $languages,
        private TransactionManager $tx,
        /**
         * The SAME deterministic synonym rules the станок runs, applied to the lookup's proposals.
         *
         * Not because this path is less trusted — because it is a SECOND writer into one table, and
         * a table whose rules live in whichever writer somebody remembered is a table with no rules.
         * The lookup barrier already screened these for language and self-reference; what it cannot
         * express is the SHAPE question («a phrase gets no synonyms, a synonym is not a paraphrase»),
         * and that question is answered in exactly one place.
         */
        private EnrichmentValidator $validator = new EnrichmentValidator(),
        /** `GENERATION_AUTO_ENRICH` — the same switch that governs the post-generation chain. */
        private bool $autoEnrich = true,
    ) {}

    public function __invoke(AddSearchResult $command): SavedSearchResult
    {
        $langs = $this->languages->forUser($command->actorId);
        $lookup = $command->lookupId !== null
            ? ($this->cache->findById($command->lookupId) ?? throw LookupNotFound::withId($command->lookupId))
            : null;

        // The language the word is being SAVED in — the lookup row's own, not the profile's. The
        // learner may have searched in a pair their profile does not name (that is what the pill is
        // for), and writing the translation under their profile language would file a Romanian
        // gloss as Russian. The profile only stands in when there is no lookup to ask.
        $savedLang = $lookup !== null ? $lookup->nativeLang : $langs->native->value;

        [$termId, $collectionId, $added, $enrolled] = $this->tx->run(
            function () use ($command, $lookup, $langs, $savedLang): array {
                $termId = $lookup !== null
                    ? $this->termFromLookup($lookup, $savedLang, $command->fixedTranslation)
                    : ($command->termId ?? throw LookupNotFound::nothingToAdd());

                // Ownership is asserted by the add itself, so an unowned folder never reaches the
                // enrolment below — a save into someone else's shelf must not enrol anything.
                //
                // «Сохранённые» is born in the pair the learner actually SEARCHED in — the lookup
                // row's, not the profile's. The profile pair only stands in when there is no lookup
                // to ask. Getting this from the profile was harmless while nothing checked it and
                // is not any more: the folder would be created as `en`, and the pair gate would
                // then refuse the Polish word the learner had just looked up (DECISIONS п. 141).
                $collectionId = $command->collectionId
                    ?? ($this->ensureDefault)(new EnsureDefaultCollection(
                        ownerId: $command->actorId,
                        sourceLang: $lookup !== null ? new LanguageCode($lookup->nativeLang) : $langs->native,
                        targetLang: $lookup !== null ? new LanguageCode($lookup->lang) : $langs->target,
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

        // Outside the transaction: these are queue jobs, and a job dispatched inside a transaction
        // that then rolls back is a worker looking for a term that does not exist.
        // The PHOTO, on EVERY save and not only the first one. Not gated on `added`, deliberately:
        // a word saved before its image query existed has just had one filled in by `ImportTerm`
        // (`ensureImageApiPrompt` never overwrites, so this only ever completes a gap), and gating
        // on «is this new» would leave exactly those words with a placeholder forever. Costing
        // nothing to repeat is what makes that safe — the attach job's readers return only terms
        // that lack an image AND carry a query, so a folder of a hundred imaged words is zero
        // searches. Not gated on `auto_enrich` either: that switch governs the paid станок, and an
        // image search is a different vendor and a different budget.
        $this->imageAttachment->dispatch($collectionId);

        // The станок, on the other hand, IS one paid model call per term — so it fires once, when
        // the word is genuinely new to this folder.
        if ($added && $this->autoEnrich) {
            $this->enrichment->enrichTerms(
                [$termId->value],
                BuildTermEnrichmentsHandler::VERSION,
                // The language the word was SAVED in, so the станок writes its distractors and
                // notes beside the translation that already exists rather than beside a second one
                // in whatever the profile happens to say.
                $savedLang,
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
    private function termFromLookup(CachedLookup $lookup, string $nativeLang, ?string $confirmed = null): TermId
    {
        $lang = new \App\Modules\Shared\Domain\ValueObject\LanguageCode($nativeLang);
        // The reading the card asks. The learner's confirmed line outranks the model's — they read
        // it and pressed the button — and the model's own becomes an alternative beside it rather
        // than being thrown away, because it is a perfectly good second reading of the same word.
        $primary = $confirmed !== null && trim($confirmed) !== '' ? trim($confirmed) : $lookup->translation;
        $alternatives = [];
        foreach ([$lookup->translation, ...$lookup->otherTranslations] as $other) {
            if (trim($other) !== '' && mb_strtolower(trim($other)) !== mb_strtolower($primary)) {
                $alternatives[] = new TranslationInput($lang, trim($other));
            }
        }

        $termId = ($this->importTerm)(new ImportTerm(
            lang: new \App\Modules\Shared\Domain\ValueObject\LanguageCode($lookup->lang),
            text: $lookup->text,
            type: $lookup->type,
            pos: null,
            // `user` and not `ai`: the model wrote the words, but the learner chose the word. The
            // catalogue's provenance columns below record which model, which prompt.
            source: 'user',
            // The confirmed (or, absent one, the model's) reading pinned, every other reading of the
            // same word beside it — additive, so a learner who types «берег» for `bank` is not told
            // they are wrong by a card that pinned «банк» (SYN-1, механика 2).
            translations: [new TranslationInput($lang, $primary, isPrimary: true), ...$alternatives],
            ipa: $lookup->transcription,
            examples: $lookup->example !== null
                // The gloss is in the learner's own language — the same one the translation above
                // is labelled with, because the lookup was asked in exactly that pair.
                ? [new ExampleInput(
                    $lookup->example,
                    $lookup->exampleTranslation,
                    new \App\Modules\Shared\Domain\ValueObject\LanguageCode($nativeLang),
                )]
                : [],
            cefr: $lookup->cefr,
            // The model's own image-search query, so a word saved from search gets a photo the same
            // way a generated one does. Null on a cache row written before the v2 prompt existed —
            // and null on a word the model refused to illustrate, which is the same outcome by
            // design: no query, no photo, never a guessed one.
            imageApiPrompt: $lookup->imageApiPrompt,
            promptVersion: $lookup->promptVersion,
            generationModel: $lookup->model,
        ));

        // THE PIN, and only when the learner confirmed one.
        //
        // `ImportTerm` above cannot move a primary that is already set — that is the rule which
        // stops a re-generation re-wording a card somebody is learning from — and this is exactly
        // the case the rule needs an exception for: the term may already exist (a global catalogue
        // word, or the same word saved before), and the learner has just read a translation and
        // agreed with it. So the authority above the generator says so explicitly.
        if ($confirmed !== null && trim($confirmed) !== '') {
            ($this->pinTranslation)(new PinTermTranslation($termId, $lang, trim($confirmed)));
        }

        // The near-synonyms the lookup came back with, on the term's own side. Written through the
        // enrichment path — one writer, one dedup, one `terms.updated_at` touch — and stamped with
        // the prompt that proposed them, like every other generated row.
        [$synonyms] = $this->validator->synonymsFor([$lookup->text], $lookup->synonyms);
        if ($synonyms !== []) {
            ($this->importEnrichment)(new ImportTermEnrichment(
                termId: $termId,
                exampleId: null,
                variants: [],
                distractors: [],
                generatorVersion: $lookup->promptVersion,
                synonyms: array_map(
                    static fn (string $text): TermSynonymInput => new TermSynonymInput($text),
                    $synonyms,
                ),
            ));
        }

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
