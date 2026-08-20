<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Service;

use App\Modules\Admin\Application\Dto\ContentHealthTermRow;
use App\Modules\Admin\Application\Dto\ContentTermFacts;
use App\Modules\Learning\Application\Dto\TermContentAssessmentView;
use App\Modules\Learning\Application\Service\ContentRequirementsResolver;

/**
 * Turns one term's raw facts into the row every «Здоровье контента» table shows.
 *
 * The only place in Admin that decides anything about content, and it decides almost nothing: what
 * a card can be built from is asked of Learning ({@see ContentRequirementsResolver}, over the same
 * gate the live session runs), and what is worth paying for is asked of {@see ContentTopUp}. What is
 * left here is the joining, which is exactly as much as a reporting projection should own.
 */
final readonly class ContentHealthAssessor
{
    public function __construct(
        private ContentRequirementsResolver $requirements,
        private ContentTopUp $topUp,
    ) {}

    public function assess(ContentTermFacts $facts): TermContentAssessmentView
    {
        return $this->requirements->forTermContent(
            $facts->text,
            $facts->exampleSentence,
            $facts->exampleTranslation,
            $facts->distractorSpans,
        );
    }

    public function row(ContentTermFacts $facts, ?TermContentAssessmentView $assessment = null): ContentHealthTermRow
    {
        $assessment ??= $this->assess($facts);
        $hasExample = $facts->exampleSentence !== null && $facts->exampleSentence !== '';
        $usable = $assessment->usableDistractors;

        return new ContentHealthTermRow(
            termId: $facts->termId,
            text: $facts->text,
            translation: $facts->translation,
            hasExample: $hasExample,
            missingExample: ! $hasExample,
            usableDistractors: $usable,
            rawDistractors: count($facts->distractorSpans),
            pickCorrectReady: $assessment->statusOf('pick_correct') === 'ok',
            variants: $facts->variantCount,
            enrichmentVersion: $facts->enrichmentVersion,
            needsEnrichment: $this->topUp->needsEnrichment($hasExample, $usable, $facts->variantCount),
            needsEnrichmentReasons: $this->topUp->reasons($hasExample, $usable, $facts->variantCount),
        );
    }

    /**
     * Most under-stocked first: a term with no example at all, then the ones with the fewest usable
     * distractors, then the fewest variants, then alphabetically so the order is stable between two
     * reads of the same data.
     *
     * @param  list<ContentHealthTermRow>  $rows
     * @return list<ContentHealthTermRow>
     */
    public function worstFirst(array $rows): array
    {
        usort($rows, static function (ContentHealthTermRow $a, ContentHealthTermRow $b): int {
            return [$a->hasExample, $a->usableDistractors, $a->variants, $a->text]
                <=> [$b->hasExample, $b->usableDistractors, $b->variants, $b->text];
        });

        return $rows;
    }
}
