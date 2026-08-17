<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\Entity;

use App\Modules\Learning\Domain\ValueObject\TriageId;
use App\Modules\Learning\Domain\ValueObject\TriageVerdict;
use App\Modules\Shared\Domain\ValueObject\CollectionId;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Shared\Domain\ValueObject\UserId;
use DateTimeImmutable;

/**
 * One immutable triage swipe, in an append-only log separate from `reviews` — a swipe is a
 * self-assessment, not an exercise answer, and mixing it into the review log would inflate
 * retention with unproven words. The current verdict for a (user, term) is the one with the
 * greatest `clientSeq` (a per-user monotonic counter set by the client) — NOT the latest by
 * `decidedAt`, which is a device clock that can lag and roll back a genuinely-later verdict.
 * A re-uploaded batch is an ignored insert (client-generated id).
 */
final class Triage
{
    public function __construct(
        public readonly TriageId $id,
        public readonly UserId $userId,
        public readonly TermId $termId,
        public readonly TriageVerdict $verdict,
        public readonly DateTimeImmutable $decidedAt,
        public readonly int $clientSeq,
        public readonly ?CollectionId $collectionId = null,
        public readonly ?int $latencyMs = null,
        public readonly ?bool $revealed = null, // flipped the card before deciding — a risk signal for «known»
    ) {}
}
