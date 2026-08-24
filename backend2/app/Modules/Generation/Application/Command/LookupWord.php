<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Command;

use App\Modules\Generation\Domain\ValueObject\TaughtSide;
use App\Modules\Shared\Domain\ValueObject\UserId;

/**
 * «Найти с ИИ» — one paid model call for a word the database does not have.
 *
 * A COMMAND rather than a query even though the learner is only looking: it may spend money and it
 * writes a cache row. Naming it a query would be the kind of small lie that ends with a screen
 * calling it on every keystroke.
 */
final readonly class LookupWord
{
    public function __construct(
        public UserId $actorId,
        public string $query,
        /** The pair the pill is set to. Null falls back to the learner's profile pair. */
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
