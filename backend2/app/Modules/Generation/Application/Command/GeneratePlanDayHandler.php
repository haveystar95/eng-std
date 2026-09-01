<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Command;

use App\Modules\Collections\Application\Command\AddTermToCollection;
use App\Modules\Collections\Application\Command\AddTermToCollectionHandler;
use App\Modules\Collections\Application\Command\CreateGeneratedCollection;
use App\Modules\Collections\Application\Command\CreateGeneratedCollectionHandler;
use App\Modules\Generation\Application\Dto\PlanDayDraft;
use App\Modules\Generation\Application\Port\DispatchesExampleRepair;
use App\Modules\Generation\Application\Port\DispatchesImageAttachment;
use App\Modules\Generation\Application\Service\PlanDayComposer;
use App\Modules\Generation\Domain\Exception\PlanDayRefused;
use App\Modules\Generation\Domain\Exception\PlanSpendNotRecorded;
use App\Modules\Generation\Domain\ValueObject\PlanDayItem;
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
                // The example is written for THIS day and is scoped to it below. It is imported
                // here as well so a brand-new term is not born without one — the scope is what
                // decides where it is shown, not whether it exists.
                examples: [new ExampleInput($item->example, $item->exampleTranslation, $support)],
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
            $this->writeFacts($termId, $item, $brief, $collectionId);

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

        return [$collectionId, $termIds];
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
    private function writeFacts(
        TermId $termId,
        PlanDayItem $item,
        PlanDayGenerationBrief $brief,
        CollectionId $collectionId,
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
