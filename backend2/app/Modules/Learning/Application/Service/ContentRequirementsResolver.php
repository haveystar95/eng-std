<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Service;

use App\Modules\Learning\Application\Dto\ModeContentStatusView;
use App\Modules\Learning\Application\Dto\TermContentAssessmentView;
use App\Modules\Learning\Domain\Service\ModeContentRequirements;
use App\Modules\Learning\Domain\ValueObject\ModeContentVerdict;

/**
 * What a term's content allows each trainer to do, for readers OUTSIDE this module.
 *
 * The same shape, and the same reason, as {@see LadderStepResolver}: the answer is derived by one
 * Domain service ({@see ModeContentRequirements}, itself built on the playability gate the live
 * session runs), and a back-office report that re-implemented those conditions over the same
 * columns would be a second copy of the rules — silently stale the day a gate moves. So the caller
 * hands over raw content and gets primitives back, and never imports Learning's Domain.
 */
final readonly class ContentRequirementsResolver
{
    public function __construct(private ModeContentRequirements $requirements) {}

    /**
     * @param  string        $text                the term's own text — the card's answer
     * @param  string|null   $example             the PINNED example sentence (the one the card shows)
     * @param  string|null   $exampleTranslation  that sentence in the learner's language
     * @param  list<string>  $distractorSpans     `error_span` of every distractor on the pinned
     *                                            example, raw — the «годные» count is derived here
     */
    public function forTermContent(
        string $text,
        ?string $example,
        ?string $exampleTranslation,
        array $distractorSpans = [],
    ): TermContentAssessmentView {
        $assessment = $this->requirements->assess($text, $example, $exampleTranslation, $distractorSpans);

        return new TermContentAssessmentView(
            usableDistractors: $assessment->usableDistractors,
            modes: array_values(array_map(
                static fn (ModeContentVerdict $verdict): ModeContentStatusView => new ModeContentStatusView(
                    mode: $verdict->mode->value,
                    status: $verdict->status->value,
                    reason: $verdict->gap?->value,
                    explanation: $verdict->explanation,
                ),
                $assessment->modes,
            )),
        );
    }
}
