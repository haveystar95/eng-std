<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Query;

use App\Modules\Admin\Application\Dto\CollectionRefRow;
use App\Modules\Admin\Application\Dto\ExampleDistractorRow;
use App\Modules\Admin\Application\Dto\ModeSimulationRow;
use App\Modules\Admin\Application\Dto\PassportDistractorRow;
use App\Modules\Admin\Application\Dto\TermContentPassport;
use App\Modules\Admin\Application\Port\AdminContentHealthReader;
use App\Modules\Admin\Application\Port\AdminTermReader;
use App\Modules\Admin\Application\Service\ContentTopUp;
use App\Modules\Learning\Application\Dto\ModeContentStatusView;
use App\Modules\Learning\Application\Service\ContentRequirementsResolver;

/**
 * One term's passport.
 *
 * Reads the term through the SAME projection the term page uses ({@see AdminTermReader}) rather than
 * a second query of its own — the two screens describe one term, and a passport that disagreed with
 * the card above it about which example is pinned would be worse than no passport. The content
 * verdicts come from Learning, the suppression history and the enrichment marks from the
 * content-health projection, and the topping-up policy from {@see ContentTopUp}. Nothing here
 * decides anything on its own.
 */
final readonly class GetTermContentPassportHandler
{
    public function __construct(
        private AdminTermReader $terms,
        private AdminContentHealthReader $content,
        private ContentRequirementsResolver $requirements,
        private ContentTopUp $topUp,
    ) {}

    public function __invoke(GetTermContentPassport $query): ?TermContentPassport
    {
        $detail = $this->terms->detail($query->termId);
        if ($detail === null) {
            return null;
        }

        // The pinned example is the only one the learner ever sees and the only one distractors hang
        // off; the projection already flags it, so this must not re-derive it.
        $pinned = null;
        foreach ($detail->examples as $example) {
            if ($example->isPinned) {
                $pinned = $example;

                break;
            }
        }

        $rows = $pinned !== null ? $pinned->distractors : [];
        $assessment = $this->requirements->forTermContent(
            $detail->text,
            $pinned?->sentence,
            $pinned?->translation,
            array_map(static fn (ExampleDistractorRow $d): string => $d->errorSpan, $rows),
        );

        $usable = array_flip($assessment->usableIndexes);
        $hasExample = $pinned !== null && $pinned->sentence !== '';

        return new TermContentPassport(
            termId: $detail->id,
            text: $detail->text,
            lang: $detail->lang,
            type: $detail->type,
            translations: $detail->translations,
            exampleId: $pinned?->id,
            exampleSentence: $pinned?->sentence,
            exampleTranslation: $pinned?->translation,
            distractors: array_map(
                static fn (ExampleDistractorRow $d, int $i): PassportDistractorRow => new PassportDistractorRow(
                    id: $d->id,
                    sentence: $d->sentence,
                    errorType: $d->errorType,
                    errorSpan: $d->errorSpan,
                    correction: $d->correction,
                    generatorVersion: $d->generatorVersion,
                    usable: isset($usable[$i]),
                ),
                $rows,
                array_keys($rows),
            ),
            suppressed: $this->content->suppressionsForTerm($detail->id),
            acceptedVariants: $detail->acceptedVariants,
            enrichmentVersions: $this->content->enrichmentVersionsForTerm($detail->id),
            enrichmentVersion: $detail->enrichmentVersion,
            findings: $detail->findings,
            simulation: array_map(
                static fn (ModeContentStatusView $m): ModeSimulationRow => new ModeSimulationRow(
                    mode: $m->mode,
                    status: $m->status,
                    reason: $m->reason,
                    explanation: $m->explanation,
                ),
                $assessment->modes,
            ),
            usableDistractors: $assessment->usableDistractors,
            missingExample: ! $hasExample,
            needsEnrichment: $this->topUp->needsEnrichment($hasExample, $assessment->usableDistractors, count($detail->acceptedVariants)),
            needsEnrichmentReasons: $this->topUp->reasons($hasExample, $assessment->usableDistractors, count($detail->acceptedVariants)),
            collections: $detail->collections,
            // Every collection holding the term: the станок resolves collections to terms, so naming
            // fewer of them can leave the term outside the run entirely.
            topUpCommand: $this->topUp->command(array_map(
                static fn (CollectionRefRow $c): string => $c->id,
                $detail->collections,
            )),
            topUpHint: $this->topUp->versionHint($detail->enrichmentVersion),
            currentGeneratorVersion: $this->topUp->currentVersion(),
            minDistractors: ContentTopUp::MIN_DISTRACTORS,
            costPerTermUsd: ContentTopUp::COST_PER_TERM_USD,
        );
    }
}
