<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\Event;

use App\Modules\Learning\Domain\Entity\Review;
use App\Modules\Shared\Domain\Event\DomainEvent;
use DateTimeImmutable;

/**
 * Raised after a batch of reviews is accepted and folded into progress. Consumed by the
 * daily-stats projector; because stats are a rebuildable projection of the reviews log,
 * this is a convenience, not a source of truth.
 */
final readonly class ReviewsSubmitted implements DomainEvent
{
    /**
     * @param  list<Review>  $accepted             reviews newly appended to the log, in answered order
     * @param  list<string>  $introducedTermIds    term ids whose first-ever review is in this batch
     */
    public function __construct(
        private DateTimeImmutable $occurredAt,
        public array $accepted,
        public array $introducedTermIds,
    ) {}

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
