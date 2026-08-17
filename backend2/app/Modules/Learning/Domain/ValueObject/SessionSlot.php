<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\ValueObject;

/**
 * One position in a session's running order: which term, and — when the term is on the
 * acquisition ladder — which rung this particular card is.
 *
 * A term can now occupy SEVERAL slots in one session. That is the whole point of the ladder: a
 * word is introduced and then comes back twice in the same sitting, at widening distances, which
 * is the only spacing a first session can offer. `ladderStep` is null for a graduated term, whose
 * single card's mode is derived from its progress as it always was.
 */
final readonly class SessionSlot
{
    public function __construct(
        public string $termId,
        public ?int $ladderStep = null,
    ) {}
}
