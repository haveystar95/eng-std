<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Dto;

/**
 * One term's content passport: everything it holds, and what each of the ten trainers can build
 * from it.
 *
 * The screen this feeds answers ONE question — «что позволяет контент этого термина». It does not
 * answer «когда этот тренажёр откроется ученику», which is the ladder's question and lives on its
 * own screen; a passport that also drew rungs would let a reader conclude that a green line here
 * means the learner will see that card, which is false in both directions.
 *
 * Two lists of wrong sentences, kept apart deliberately: `distractors` are the LIVE rows the card
 * could deal, and `suppressed` are sentences a proofreader or the audit threw out. The second list
 * outlives the rows it describes — that is the whole point of `enrichment_suppressions` — so it is
 * shown as its own history, never merged into the first.
 */
final readonly class TermContentPassport
{
    /**
     * @param  list<TermTranslationRow>  $translations
     * @param  list<PassportDistractorRow>  $distractors  live rows on the PINNED example
     * @param  list<array{sentence: string, source: string, created_at: string|null}>  $suppressed
     * @param  list<TermVariantRow>  $acceptedVariants
     * @param  list<array{version: string, created_at: string|null}>  $enrichmentVersions
     * @param  list<EnrichmentFindingRow>  $findings
     * @param  list<ModeSimulationRow>  $simulation  every trainer this build knows, enum order
     * @param  list<CollectionRefRow>  $collections
     * @param  list<string>  $needsEnrichmentReasons  `few_distractors` | `no_variants`
     */
    public function __construct(
        public string $termId,
        public string $text,
        public string $lang,
        public string $type,
        public array $translations,
        public ?string $exampleId,
        public ?string $exampleSentence,
        public ?string $exampleTranslation,
        public array $distractors,
        public array $suppressed,
        public array $acceptedVariants,
        public array $enrichmentVersions,
        public ?string $enrichmentVersion,
        public array $findings,
        public array $simulation,
        public int $usableDistractors,
        public bool $missingExample,
        public bool $needsEnrichment,
        public array $needsEnrichmentReasons,
        public array $collections,
        public string $topUpCommand,
        public ?string $topUpHint,
        public string $currentGeneratorVersion,
        public int $minDistractors,
        public float $costPerTermUsd,
    ) {}
}
