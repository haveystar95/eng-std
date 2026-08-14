<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Dto;

use App\Modules\Learning\Domain\ValueObject\StudySessionId;
use App\Modules\Shared\Domain\ValueObject\TermId;
use DateTimeImmutable;

/**
 * One intro card, as the client uploads it: the word was SHOWN. There is no answer, no grade and
 * no latency, because the card asked for nothing — which is the entire difference between this and
 * a {@see ReviewInput}, and the reason it is a separate type rather than a review with null fields.
 *
 * It deliberately carries no `client_seq`. Sequence numbers exist to order events whose ORDER
 * changes the outcome — the review fold is replayable only because of them. An exposure has no
 * such order: it is idempotent on `(user, term)`, it is applied before the batch's answers, and a
 * second one for the same pair is not a later event but the same one re-sent.
 */
final readonly class ExposureInput
{
    public function __construct(
        public TermId $termId,
        public DateTimeImmutable $shownAt,
        public ?StudySessionId $sessionId = null,
    ) {}
}
