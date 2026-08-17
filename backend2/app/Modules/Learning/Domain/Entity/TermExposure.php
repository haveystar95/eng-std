<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\Entity;

use App\Modules\Learning\Domain\ValueObject\StudySessionId;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Shared\Domain\ValueObject\UserId;
use DateTimeImmutable;

/**
 * The record that a term was SHOWN to a user for the first time — the intro card's only output.
 *
 * It exists because the intro must not be written to `reviews`. That log holds real retrievals and
 * is what retention, the latency medians and the whole notion of "how well do you know this" are
 * computed from; an intro asks the learner for nothing, so a row there would be a retrieval that
 * never happened, quietly inflating every one of those figures.
 *
 * Its identity is the PAIR, not an event id: `(user_id, term_id)`. A term is introduced once, and
 * the second intro of the same word is not a second fact — it is the same fact re-uploaded by a
 * device that lost its acknowledgement. So the write is an ignored insert and idempotency is a
 * property of the schema rather than of the handler that happens to be running.
 */
final readonly class TermExposure
{
    public function __construct(
        public UserId $userId,
        public TermId $termId,
        public DateTimeImmutable $shownAt,
        public ?StudySessionId $sessionId = null,
    ) {}
}
