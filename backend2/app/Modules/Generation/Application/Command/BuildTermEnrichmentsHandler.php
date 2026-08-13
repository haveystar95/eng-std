<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Command;

use App\Modules\Generation\Application\Dto\EnrichmentBrief;
use App\Modules\Generation\Application\Dto\EnrichmentRunMetrics;
use App\Modules\Generation\Application\Port\EnrichmentJournal;
use App\Modules\Generation\Application\Port\EnrichmentPackerPort;
use App\Modules\Generation\Application\Port\RecordsTermEnrichment;
use App\Modules\Generation\Domain\Service\EnrichmentValidator;
use App\Modules\Generation\Domain\ValueObject\EnrichmentCandidate;
use App\Modules\Generation\Domain\ValueObject\EnrichmentVerdict;
use App\Modules\Generation\Domain\ValueObject\FindingKind;
use App\Modules\Shared\Domain\Service\Clock;
use App\Modules\Shared\Domain\Service\ModelCost;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Vocabulary\Application\Command\ImportTermEnrichment;
use App\Modules\Vocabulary\Application\Command\ImportTermEnrichmentHandler;
use App\Modules\Vocabulary\Application\Dto\AcceptedVariantInput;
use App\Modules\Vocabulary\Application\Dto\EnrichmentTargetView;
use App\Modules\Vocabulary\Application\Dto\ExampleDistractorInput;
use App\Modules\Vocabulary\Application\Query\EnrichmentTargetReader;
use Throwable;

/**
 * One chunk of the станок, term by term: read the content (batched), ask the model once, validate
 * deterministically, write what survived, journal what a human must see, mark the term done.
 *
 * Two deliberate choices about failure:
 *
 *  - the version mark is written per TERM, immediately after that term is stored, not once for the
 *    chunk. A chunk that dies on term 7 of 20 is resumed by re-dispatching it: terms 1–6 are
 *    skipped by `pending()` and never re-paid for;
 *  - a single term that throws (a malformed pack, an unparseable response) is counted and skipped,
 *    NOT propagated. One bad term must not take down the other nineteen. Genuinely transient
 *    failures — the API being down — surface from the packer as exceptions on every term in the
 *    chunk, and `termsFailed == termsSeen` is the signal the caller reports.
 *
 * Cross-module work goes through Vocabulary's Application only; this handler never sees a table.
 */
final readonly class BuildTermEnrichmentsHandler
{
    /**
     * The станок's current generator version — one source of truth for everything that writes or
     * skips by it (the console backfill, the post-generation chain). Bump it when the prompt or the
     * validation rules change in a way that makes a re-run worth paying for; every already-processed
     * term is then pending again at the new version.
     */
    public const VERSION = 'enrich-v1';

    public function __construct(
        private EnrichmentTargetReader $targets,
        private EnrichmentPackerPort $packer,
        private EnrichmentValidator $validator,
        private ImportTermEnrichmentHandler $import,
        private EnrichmentJournal $journal,
        private RecordsTermEnrichment $spend,
        private Clock $clock,
        private ModelCost $cost = new ModelCost(),
    ) {}

    public function __invoke(BuildTermEnrichments $command): EnrichmentRunMetrics
    {
        $pending = $this->journal->pending($command->termIds, $command->generatorVersion);
        if ($pending === []) {
            return new EnrichmentRunMetrics();
        }

        $targets = $this->targets->byIds(
            array_map(static fn (string $id): TermId => TermId::fromString($id), $pending),
            $command->translationLang,
        );

        $metrics = new EnrichmentRunMetrics();
        foreach ($pending as $termId) {
            $target = $targets[$termId] ?? null;
            if ($target === null) {
                // The term vanished between listing and processing (deleted collection, dedup
                // merge). Nothing to do and nothing to fix — mark it so we stop looking at it.
                $this->journal->markDone($termId, $command->generatorVersion);

                continue;
            }

            try {
                $metrics = $metrics->plus($this->enrichOne($target, $command->generatorVersion));
            } catch (Throwable) {
                $metrics = $metrics->plus(new EnrichmentRunMetrics(termsSeen: 1, termsFailed: 1));
            }
        }

        return $metrics;
    }

    private function enrichOne(EnrichmentTargetView $target, string $version): EnrichmentRunMetrics
    {
        $pack = $this->packer->pack(new EnrichmentBrief(
            termId: $target->termId,
            text: $target->text,
            acceptedForms: $target->acceptedForms,
            translation: $target->translation,
            exampleSentence: $target->exampleSentence,
            exampleTranslation: $target->exampleTranslation,
            termLang: $target->lang,
            // A term with no translation has no prompt side; ru is the only learner language the
            // content actually has today, and the brief needs *a* value. If a second one ever shows
            // up, it arrives with translations, so this fallback stays unreached.
            translationLang: $target->translationLang ?? 'ru',
        ));

        // Spend is recorded the instant the call answers, BEFORE validation — the same rule
        // GenerationPipeline follows, and for the same reason: a pack the validator throws away cost
        // exactly what a good one costs, and a ledger that only booked successful packs would make
        // the bad runs look free. Without this row the станок spends money nothing accounts for: the
        // admin's per-collection cost reads `term_enrichments`, and it stopped being written when the
        // pack path replaced the one-term path, so every collection has reported станок = 0 since
        // 2026-08-06 while the calls kept happening.
        $this->spend->record(
            TermId::fromString($target->termId),
            $pack->model,
            $pack->tokensIn,
            $pack->tokensOut,
            $this->cost->estimate($pack->model, $pack->tokensIn, $pack->tokensOut),
            $this->clock->now(),
        );

        $verdict = $this->validator->validate(new EnrichmentCandidate(
            termId: $target->termId,
            acceptedForms: $target->acceptedForms,
            exampleId: $target->exampleId,
            exampleSentence: $target->exampleSentence,
            translation: $target->translation,
            exampleTranslation: $target->exampleTranslation,
            distractors: $pack->distractors,
            variants: $pack->variants,
            backTranslation: $pack->backTranslation,
            languageNotes: $pack->languageNotes,
            existingDistractors: $target->existingDistractors,
        ));

        ($this->import)(new ImportTermEnrichment(
            termId: TermId::fromString($target->termId),
            exampleId: $target->exampleId,
            variants: array_map(
                static fn ($v): AcceptedVariantInput => new AcceptedVariantInput($v->text, $v->note),
                $verdict->variants,
            ),
            distractors: array_map(
                static fn ($d): ExampleDistractorInput => new ExampleDistractorInput(
                    $d->sentence, $d->errorType, $d->errorSpan, $d->correction,
                ),
                $verdict->distractors,
            ),
            generatorVersion: $version,
        ));

        $this->journal->recordFindings($verdict->findings, $version);
        $this->journal->markDone($target->termId, $version);

        return $this->measure($verdict, $target->exampleId !== null && $target->exampleSentence !== null);
    }

    private function measure(EnrichmentVerdict $verdict, bool $hasExample): EnrichmentRunMetrics
    {
        return new EnrichmentRunMetrics(
            termsSeen: 1,
            termsWithExample: $hasExample ? 1 : 0,
            // Counted only where an example exists: a term with no example is not an example short of
            // distractors, it is a term with nothing to build them against.
            examplesUnderTwoDistractors: $hasExample && count($verdict->distractors) < 2 ? 1 : 0,
            distractorsProposed: $verdict->proposedDistractors,
            distractorsRejected: $verdict->rejectedDistractors,
            distractorsWritten: count($verdict->distractors),
            variantsWritten: count($verdict->variants),
            variantsRejected: $verdict->rejectedVariants,
            termsAmbiguous: $verdict->hasFinding(FindingKind::Ambiguity) ? 1 : 0,
            // "Any language problem" is the union of the three kinds, so a term that only has a
            // nonword still counts once in the headline language rate.
            termsLanguageFlagged: $verdict->hasLanguageFinding() ? 1 : 0,
            termsVariantConflict: $verdict->hasFinding(FindingKind::VariantConflict) ? 1 : 0,
            termsUaLeakage: $verdict->hasFinding(FindingKind::UaLeakage) ? 1 : 0,
            termsMisspelled: $verdict->hasFinding(FindingKind::MisspelledOrNonword) ? 1 : 0,
        );
    }
}
