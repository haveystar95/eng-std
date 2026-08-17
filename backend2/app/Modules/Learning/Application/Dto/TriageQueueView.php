<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Dto;

/**
 * A page of the triage queue plus how many eligible terms remain beyond it.
 *
 * `remaining` is the count of not-yet-triaged, not-yet-studied terms left AFTER the cards in
 * this response — i.e. what subsequent GETs will still serve, not "all new terms in the
 * collection". Computed after the same exclusion the cards go through, so the client's honest
 * progress can't diverge from the real queue.
 */
final readonly class TriageQueueView
{
    /** @param list<TriageCardView> $cards */
    public function __construct(
        public array $cards,
        public int $remaining,
    ) {}
}
