<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Dto;

/**
 * THE MORNING OF THE EVENT — «6 фраз за 3 минуты» (кадр 1c · 15).
 *
 * Phrases and NOTHING ELSE, which is the whole design of this screen: three minutes before walking
 * in, the point is to hear yourself say the sentence, not to be tested on the words inside it. No
 * options, no typing, no new material, and no scheduling — a rehearsal moves nothing on the ladder.
 */
final readonly class PlanRehearsalView
{
    /** @param list<PlanRehearsalLineView> $lines */
    public function __construct(
        public string $planId,
        public string $title,
        public array $lines,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'plan_id' => $this->planId,
            'title' => $this->title,
            'lines' => array_map(
                static fn (PlanRehearsalLineView $l): array => $l->toArray(),
                $this->lines,
            ),
        ];
    }
}
