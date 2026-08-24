<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Query;

use App\Modules\Generation\Domain\ValueObject\TaughtSide;
use App\Modules\Shared\Domain\ValueObject\UserId;

/**
 * «What does this word mean?» — the cheapest possible answer, while the learner is still typing.
 *
 * A QUERY and not a command even though it can write a cache row: from the caller's side nothing
 * about the learner's world changes — no term, no folder, no pool, no quota of theirs. What it
 * writes is a fact about a WORD, shared by everyone, and it writes it so that the next person does
 * not have to buy it again.
 */
final readonly class InstantTranslate
{
    public function __construct(
        public UserId $actorId,
        public string $query,
        /**
         * The pair the learner set on the pill: what they typed in, what they want back.
         *
         * Null for a caller with no pill — the learner's profile pair stands in. Both or neither;
         * half a pair is treated as none, because a direction nobody chose is worse than the
         * default one.
         */
        public ?string $source = null,
        public ?string $target = null,
        /**
         * Which half of the pair the learner is STUDYING, named by the client rather than guessed.
         *
         * Null keeps the old behaviour — the tie-break in {@see \App\Modules\Generation\Application\Service\SearchPair}
         * (DECISIONS п. 147) — which is what a build from before this field, and any caller with no
         * pill at all, will always send.
         */
        public ?TaughtSide $taughtSide = null,
    ) {}
}
