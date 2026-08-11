<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Command;

use App\Modules\Generation\Application\Dto\EnrichmentBrief;
use App\Modules\Generation\Application\Dto\EnrichmentRunMetrics;
use App\Modules\Generation\Application\Port\EnrichmentJournal;
use App\Modules\Generation\Application\Port\EnrichmentPackerPort;
use App\Modules\Generation\Domain\Service\EnrichmentValidator;
use App\Modules\Generation\Domain\ValueObject\EnrichmentCandidate;
use App\Modules\Generation\Domain\ValueObject\EnrichmentVerdict;
use App\Modules\Generation\Domain\ValueObject\FindingKind;
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
    ) {}

    public function __invoke(BuildTermEnrichments $command): EnrichmentRunMetrics
    {
        $pending = $this->journal->pending($command->termIds, $command->generatorVersion);
        if ($pending === []) {
            return new EnrichmentRunMetrics();
        }

        $targets = $this->targets->byIds(array_map(
            static fn (string $id): TermId => TermId::fromString($id),
            $pending,
        ));

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
        ));

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

        return $this->measure($verdict);
    }

    private function measure(EnrichmentVerdict $verdict): EnrichmentRunMetrics
    {
        return new EnrichmentRunMetrics(
            termsSeen: 1,
            distractorsProposed: $verdict->proposedDistractors,
            distractorsRejected: $verdict->rejectedDistractors,
            distractorsWritten: count($verdict->distractors),
            variantsWritten: count($verdict->variants),
            termsAmbiguous: $verdict->hasFinding(FindingKind::Ambiguity) ? 1 : 0,
            termsLanguageFlagged: $verdict->hasFinding(FindingKind::Language) ? 1 : 0,
            termsVariantConflict: $verdict->hasFinding(FindingKind::VariantConflict) ? 1 : 0,
        );
    }
}
