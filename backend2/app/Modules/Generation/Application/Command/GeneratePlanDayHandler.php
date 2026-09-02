<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Command;

use App\Modules\Collections\Application\Command\AddTermToCollection;
use App\Modules\Collections\Application\Command\AddTermToCollectionHandler;
use App\Modules\Collections\Application\Command\CreateGeneratedCollection;
use App\Modules\Collections\Application\Command\CreateGeneratedCollectionHandler;
use App\Modules\Generation\Application\Dto\PlanDayDraft;
use App\Modules\Generation\Application\Port\DispatchesExampleRepair;
use App\Modules\Generation\Application\Port\RescueKitSource;
use App\Modules\Generation\Application\Port\DispatchesImageAttachment;
use App\Modules\Generation\Application\Service\PlanDayComposer;
use App\Modules\Generation\Domain\Exception\PlanDayRefused;
use App\Modules\Generation\Domain\Exception\PlanSpendNotRecorded;
use App\Modules\Generation\Domain\Service\PlanSpeakingKey;
use App\Modules\Generation\Domain\ValueObject\PlanDayItem;
use App\Modules\Generation\Domain\ValueObject\PlanShelf;
use App\Modules\Generation\Domain\ValueObject\RescuePhrase;
use App\Modules\Learning\Application\Command\ClaimPlanDay;
use App\Modules\Learning\Application\Command\ClaimPlanDayHandler;
use App\Modules\Learning\Application\Command\FinishPlanDay;
use App\Modules\Learning\Application\Command\FinishPlanDayHandler;
use App\Modules\Learning\Application\Dto\PlanDayGenerationBrief;
use App\Modules\Shared\Domain\Service\DifficultyScorer;
use App\Modules\Shared\Domain\Service\TransactionManager;
use App\Modules\Shared\Domain\ValueObject\CollectionId;
use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Vocabulary\Application\Command\ImportTerm;
use App\Modules\Vocabulary\Application\Command\ImportTermHandler;
use App\Modules\Vocabulary\Application\Dto\ExampleInput;
use App\Modules\Vocabulary\Application\Dto\TranslationInput;
use App\Modules\Vocabulary\Application\Port\TermDescriptionWriter;
use App\Modules\Vocabulary\Application\Port\TermExampleScopeWriter;
use App\Modules\Vocabulary\Application\Port\TermPlanFactsWriter;
use App\Modules\Vocabulary\Application\Port\TermTransliterationWriter;
use App\Modules\Vocabulary\Application\Query\KnownTermsReader;
use Throwable;

/**
 * ONE DAY OF A PLAN, written.
 *
 * A day of a plan NEVER goes through `generate_collection`. Two prompts that both produce «a list
 * of terms» look interchangeable and are not: the core generator picks words about a TOPIC and
 * wants 60–70% multi-word expressions; a plan day writes the REPLIES of one conversation with
 * enough substitution words to say them, and wants 45%. Those two numbers point in opposite
 * directions, and routing a day through the core would mean one of them dying silently
 * (docs/research/plan-sandbox-2026-08-29.md §7.4).
 *
 * What the day DOES share is everything after the material exists: the collection is an ordinary
 * collection, the terms are ordinary terms, and the enrichment станок runs over it exactly as it
 * runs over any other. That is the whole point of a day owning a collection — every trainer and
 * every exercise mode keeps working without knowing plans exist.
 *
 * ## The order of operations, and why
 *
 * 1. **Claim the day** — locked, one worker wins ({@see ClaimPlanDayHandler}).
 * 2. **Read what the learner already knows** on this pair, so the prompt can be told not to
 *    re-teach it.
 * 3. **Call the model** — outside any transaction, like every other vendor call here.
 * 4. **Validate** — {@see \App\Modules\Generation\Domain\Service\PlanDayValidator}. A day that
 *    fails goes back for exactly one re-run.
 * 5. **Write**, in one transaction: the collection, the terms, the day-scoped examples.
 * 6. **Finish** — the day is ready, its terms are strictly enrolled, the next day is queued.
 * 7. **Chain the станок and the pictures**, fire-and-forget, exactly as a finished generation does.
 *
 * Every failure from step 3 onward is reported through {@see FinishPlanDay} rather than thrown, so
 * the attempt is recorded against the day and the reason survives. A thrown exception would let the
 * queue retry a day that has already spent an attempt, which is how one broken day becomes six paid
 * calls.
 */
final readonly class GeneratePlanDayHandler
{
    public function __construct(
        private ClaimPlanDayHandler $claim,
        private FinishPlanDayHandler $finish,
        private PlanDayComposer $composer,
        private KnownTermsReader $knownTerms,
        private CreateGeneratedCollectionHandler $createCollection,
        private AddTermToCollectionHandler $addTerm,
        private ImportTermHandler $importTerm,
        private TermExampleScopeWriter $scopedExamples,
        private TermPlanFactsWriter $planFacts,
        private TermDescriptionWriter $descriptions,
        private TermTransliterationWriter $transliterations,
        private DispatchesExampleRepair $repairExamples,
        private DispatchesImageAttachment $attachImages,
        private DifficultyScorer $scorer,
        private TransactionManager $tx,
        /**
         * The language pack's five phrases (канон §5). Null on a build with no pack wired, and then
         * day 1 is written without a kit rather than refused — the same shape every «this language
         * has no rule yet» takes in the plan path.
         */
        private ?RescueKitSource $rescueKit = null,
    ) {}

    public function __invoke(GeneratePlanDay $command): void
    {
        $brief = ($this->claim)(new ClaimPlanDay($command->planId, $command->dayIndex));
        if ($brief === null) {
            // Somebody else has it, it is already written, or both attempts are spent. Not an
            // error — the job's whole contract is that running it twice costs one day.
            return;
        }

        try {
            $draft = $this->composer->compose($brief, $this->knownFor($brief));
        } catch (PlanSpendNotRecorded $e) {
            // THE ONE FAILURE THAT IS NOT TURNED INTO A DAY STATE. Everything else here becomes a
            // `fail_reason` a person can read on the plan screen, because a day that did not
            // generate is a product problem. An unrecorded payment is not: the call already
            // happened, and letting the pipeline carry on would make the money invisible exactly
            // as it was invisible in the PLAN-1a run. Out through the job, into `failed_jobs`.
            throw $e;
        } catch (Throwable $e) {
            ($this->finish)(new FinishPlanDay(
                planId: $brief->planId,
                dayIndex: $brief->dayIndex,
                collectionId: null,
                failReason: $e->getMessage(),
                // A REFUSED answer hands its verdict over as data so the next attempt can be told
                // all of it — `fail_reason` is prose and is cut at 500 characters, and the live day
                // that made this necessary produced eighteen violations in one answer. A vendor
                // failure has no verdict and carries nothing.
                failViolations: $e instanceof PlanDayRefused ? $e->violations : [],
                // The REPAIR, charged in its own column. It is not an attempt: a day that spent P2
                // and P2R has made ONE day call and still has its second, which is exactly what
                // `MAX_ATTEMPTS` was written to allow (Д-18). A vendor failure repaired nothing.
                repairCalls: $e instanceof PlanDayRefused ? $e->repairCalls : 0,
                // The one machine-readable word of the verdict, for the screen the owner reads.
                // A vendor failure has no verdict, so it carries none and the client falls back to
                // «не удалось собрать день» — which is exactly what happened (Д-19).
                failCode: $e instanceof PlanDayRefused ? $e->violationCode : null,
            ));

            return;
        }

        try {
            [$collectionId, $termIds] = $this->tx->run(
                fn (): array => $this->materialize($brief, $draft),
            );
        } catch (Throwable $e) {
            ($this->finish)(new FinishPlanDay(
                planId: $brief->planId,
                dayIndex: $brief->dayIndex,
                collectionId: null,
                failReason: 'запись дня не удалась: ' . $e->getMessage(),
            ));

            return;
        }

        ($this->finish)(new FinishPlanDay(
            planId: $brief->planId,
            dayIndex: $brief->dayIndex,
            collectionId: $collectionId->value,
            termIds: $termIds,
            repairCalls: $draft->repairCalls,
        ));

        // The ordinary chain, after the learner's day is already usable: repair whatever example
        // was refused for echoing its term, then build the exercise machinery on top. Same shape
        // as a finished generation, and for the same reason — it must not be able to fail this.
        $this->repairExamples->repairThenEnrich(
            $collectionId,
            $draft->ownerId,
            BuildTermEnrichmentsHandler::VERSION,
        );

        // THE PICTURES. Same call a finished collection generation makes, and it was missing here:
        // PLAN-1a wired the enrichment chain and not this one, so every day of every plan the owner
        // ran came out with no illustration at all and nothing said so. Fire-and-forget, after the
        // day is already usable, exactly like the chain above — a day must not fail because a
        // photo did not arrive.
        $this->attachImages->dispatch($collectionId);
    }

    /**
     * The collection, the terms and the day-scoped examples.
     *
     * @return array{0: CollectionId, 1: list<string>}
     */
    private function materialize(PlanDayGenerationBrief $brief, PlanDayDraft $draft): array
    {
        $support = new LanguageCode($brief->supportLang);
        $target = new LanguageCode($brief->targetLang);

        $collectionId = ($this->createCollection)(new CreateGeneratedCollection(
            ownerId: $draft->ownerId,
            title: $brief->dayTitle,
            sourceLang: $support,
            targetLang: $target,
            description: $draft->dayDescription,
            topic: $brief->goalText,
            // A PLAN DAY, and the folder says so. It is an ordinary private collection in every
            // other respect — that is what lets the session machinery deal its cards unchanged —
            // and without the tag «Мои коллекции» listed it and the home screen's word-challenge
            // took its replies as wrong answers, plan running or long archived (Д-34, Д-35).
            origin: CreateGeneratedCollection::ORIGIN_PLAN,
        ));

        $termIds = [];
        foreach ($draft->items as $item) {
            $termId = ($this->importTerm)(new ImportTerm(
                lang: $target,
                text: $item->text,
                type: $item->type,
                pos: null,
                source: 'ai',
                translations: [new TranslationInput($support, $item->translation, isPrimary: true)],
                ipa: null,
                // NO EXAMPLE HERE, and that is the fix rather than an omission (Д-29, «побочно»).
                // The sentence is written for THIS day and is stored by the SCOPED writer below,
                // with its translation in `example_translations`. Importing it here as well wrote a
                // second, unscoped row that carried no translation — every plan term came out with
                // two example rows, one of them half a card, and the reader picks whichever it
                // finds. One sentence, one row: the scope decides where it is shown, and a term
                // whose only example is scoped is still a term with an example
                // ({@see \App\Modules\Vocabulary\Infrastructure\Eloquent\EloquentTermContentReader}
                // ranks the day's own first and falls back to whatever else exists).
                examples: [],
                cefr: null,
                promptVersion: $draft->promptVersion,
                generationModel: $draft->model,
                // THE PICTURE. A plan day used to import its terms without one, so
                // `AttachCollectionImagesHandler` found nothing to search on even when it ran —
                // and it never ran, because nothing dispatched it for a plan. Both halves of that
                // defect are fixed here and in `__invoke()`; a card of a plan is a card, and a
                // card without a picture is a worse card for no reason anyone chose.
                imageApiPrompt: $item->imageApiPrompt !== '' ? $item->imageApiPrompt : null,
            ));

            ($this->addTerm)(new AddTermToCollection($collectionId, $termId, $draft->ownerId));
            $this->writeFacts($termId, $item, $brief, $collectionId, $draft->items);

            $termIds[] = $termId->value;
        }

        // The terms the learner already met on an earlier day of this plan: NOT re-taught, not
        // added to the collection, not counted against the budget. They get 1–2 fresh examples in
        // THIS day's situation, scoped to this day — a known term re-met in yesterday's context
        // teaches nothing.
        foreach ($draft->knownExamples as $known) {
            $this->scopedExamples->write(
                TermId::fromString($known['term_id']),
                $known['example'],
                $known['example_translation'],
                $support->value,
                $collectionId,
            );
        }

        // THE RESCUE KIT — five phrases, written into DAY 1 and into no other day (канон §5).
        //
        // The server's own cards, not the model's: they are the same five in every plan of a
        // language, and a model asked for them a hundred times spells them a hundred ways. They
        // ride in the day's collection like any other card, so the session machinery deals them
        // without knowing what they are, and they are told apart by their SHELF — which is also
        // how the warm-up finds them every morning after.
        if ($brief->dayIndex === 1) {
            foreach ($this->rescueKit?->forPair($brief->targetLang, $brief->supportLang) ?? [] as $phrase) {
                $termIds[] = $this->writeRescuePhrase($phrase, $brief, $collectionId, $draft, $support, $target)->value;
            }
        }

        return [$collectionId, $termIds];
    }

    /**
     * ONE RESCUE PHRASE, written as an ordinary card of day 1.
     *
     * Ordinary in every way that matters — a term, a translation, a scoped example, a picture query
     * — and marked in exactly one: {@see PlanShelf::Rescue}, which is what makes it
     * {@see \App\Modules\Learning\Application\Command\BuildPlanSessionHandler}'s warm-up rather
     * than one more line of the scene. No frame and no filler: «Повторите ещё раз» is said whole,
     * and a hole in it would be a hole in the one card that has to come out right under pressure.
     */
    private function writeRescuePhrase(
        RescuePhrase $phrase,
        PlanDayGenerationBrief $brief,
        CollectionId $collectionId,
        PlanDayDraft $draft,
        LanguageCode $support,
        LanguageCode $target,
    ): TermId {
        $termId = ($this->importTerm)(new ImportTerm(
            lang: $target,
            text: $phrase->text,
            type: 'phrase',
            pos: null,
            source: 'ai',
            translations: [new TranslationInput($support, $phrase->translation, isPrimary: true)],
            ipa: null,
            examples: [],
            cefr: null,
            promptVersion: $draft->promptVersion,
            generationModel: $draft->model,
            imageApiPrompt: $phrase->imageApiPrompt !== '' ? $phrase->imageApiPrompt : null,
        ));

        ($this->addTerm)(new AddTermToCollection($collectionId, $termId, $draft->ownerId));

        $this->planFacts->write(
            $termId,
            isLine: true,
            difficultyScore: $this->scorer->score($brief->targetLang, $phrase->text),
            kind: PlanDayItem::KIND_LINE,
            frame: '',
            speaker: PlanDayItem::SPEAKER_LEARNER,
            filler: '',
            // The whole phrase is the ask. There is no piece to pick out of «Секунду, я проверю»,
            // and a key that named one would grade the learner on half a formula.
            speakingKey: null,
            shelf: PlanShelf::Rescue->value,
            tier: PlanShelf::Rescue->tier(),
            skillRef: null,
            numberValue: null,
        );

        if ($phrase->example !== '') {
            $this->scopedExamples->write(
                $termId,
                $phrase->example,
                $phrase->exampleTranslation,
                $support->value,
                $collectionId,
            );
        }

        if ($phrase->transliteration !== null) {
            $this->transliterations->ensure(
                $termId,
                $brief->supportLang,
                $phrase->transliteration,
                generatorVersion: PlanDayComposer::PROMPT_VERSION,
            );
        }

        return $termId;
    }

    /**
     * The per-term side products: the day scope on the example, the two plan facts, and the two
     * side fields the core writes for every term.
     *
     * `is_line` and `difficulty_score` are written for EVERY term, including one the store already
     * had. They are facts about the language rather than about this learner's plan, and a re-used
     * term that came from a collection has never been asked either question — the answer «false,
     * null» on it is absence, not a considered no.
     */
    /** @param list<PlanDayItem> $dayItems every card of the day — what picks a line's speaking key */
    private function writeFacts(
        TermId $termId,
        PlanDayItem $item,
        PlanDayGenerationBrief $brief,
        CollectionId $collectionId,
        array $dayItems,
    ): void {
        $this->planFacts->write(
            $termId,
            isLine: $item->isLine,
            difficultyScore: $this->scorer->score($brief->targetLang, $item->text),
            // What the card DOES in this day, the frame it stands in, what stands in its hole, and
            // whose turn it is. The session reads the first three: `kind` picks the stage
            // checklist, `frame` is where the cloze cuts its gap, and `filler` is the string that
            // gap blanks.
            kind: $item->kind,
            // A FORMULA STORES NO FRAME. Since v0.3 its `frame` is the whole line — the model
            // writes one for every line — and a cloze gap cut from a frame with no hole would
            // blank nothing at all. Downstream «no hole» and «no frame» are the same state.
            frame: $item->hasSlot() ? $item->frame : '',
            speaker: $item->speaker,
            filler: $item->hasSlot() ? $item->filler : '',
            // WHAT THE SPOKEN CARD ASKS FOR. Decided here because only the code that writes the DAY
            // can see all of it: the filler, or failing that a word or connector of this same day
            // standing inside the line. {@see PlanSpeakingKey}
            speakingKey: PlanSpeakingKey::of($item, $dayItems),
            // THE SHELF AND THE TIER — the v0.4 pair the session reads before anything else. The
            // shelf is the caption a card is dealt under and the thing `kind` cannot say (say and
            // ask are both spoken `line`s, hear is a `line` nobody says); the tier is derived from
            // it by the server and stored, so no reader ever computes it a second way.
            shelf: $item->arrayName(),
            tier: $item->tier(),
            skillRef: $item->skillRef,
            // Only a `numbers` card has one, and it is what NUM-1 will grade against: the digits
            // never appear on the screen, so nothing but a gate can notice them being wrong.
            numberValue: $item->value,
        );

        $this->scopedExamples->write(
            $termId,
            $item->example,
            $item->exampleTranslation,
            $brief->supportLang,
            $collectionId,
        );

        if ($item->description !== '') {
            $this->descriptions->ensure(
                $termId,
                $brief->targetLang,
                $item->description,
                source: 'ai',
                promptVersion: PlanDayComposer::PROMPT_VERSION,
                generationModel: null,
            );
        }

        // Already normalised and alphabet-checked by the validator — a hint that survived that is
        // written, and one that did not never reaches here.
        if ($item->transliteration !== null && $item->transliteration !== '') {
            $this->transliterations->ensure(
                $termId,
                $brief->supportLang,
                $item->transliteration,
                generatorVersion: PlanDayComposer::PROMPT_VERSION,
            );
        }
    }

    /**
     * Terms this learner has already met on an earlier day of THIS plan, on this language pair.
     *
     * @return array<string, string> term id → text
     */
    private function knownFor(PlanDayGenerationBrief $brief): array
    {
        if ($brief->dayIndex <= 1) {
            return [];
        }

        return $this->knownTerms->metInPlan($brief->planId, $brief->targetLang, $brief->supportLang);
    }
}
