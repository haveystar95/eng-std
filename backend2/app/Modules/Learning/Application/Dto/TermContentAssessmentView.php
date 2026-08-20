<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Dto;

/**
 * What one term's content allows, for a reader outside Learning: the number of distractors the
 * cards can actually use, and a verdict per trainer in the enum's own order.
 */
final readonly class TermContentAssessmentView
{
    /** @param  list<ModeContentStatusView>  $modes  every trainer this build knows, enum order */
    public function __construct(
        public int $usableDistractors,
        public array $modes,
    ) {}

    public function statusOf(string $mode): ?string
    {
        foreach ($this->modes as $view) {
            if ($view->mode === $mode) {
                return $view->status;
            }
        }

        return null;
    }
}
