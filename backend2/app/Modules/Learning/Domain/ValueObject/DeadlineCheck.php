<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\ValueObject;

/**
 * A1 asked again, mid-plan: does what is LEFT still fit in the days that are left?
 *
 * The answer is a FLAG and a list, never a cut. A learner who fell three days behind gets a card
 * that says which abilities are now at risk and lets them decide — shorten, or accept. Quietly
 * dropping day 4 because the maths stopped working would be the app changing the promise it sold
 * without saying so.
 */
final readonly class DeadlineCheck
{
    /** @param list<PlanSkill> $atRisk abilities that no longer fit, in the order they would be lost */
    public function __construct(
        public int $needRemaining,
        public int $capacity,
        public int $daysRemaining,
        public int $introDaysRemaining,
        public bool $deadlineTight,
        public array $atRisk,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'need_remaining' => $this->needRemaining,
            'capacity' => $this->capacity,
            'days_remaining' => $this->daysRemaining,
            'intro_days_remaining' => $this->introDaysRemaining,
            'deadline_tight' => $this->deadlineTight,
            'at_risk' => array_map(
                static fn (PlanSkill $s): array => ['outcome' => $s->outcome, 'est_terms' => $s->estTerms],
                $this->atRisk,
            ),
        ];
    }
}
