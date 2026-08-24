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
        /**
         * The learner pressed «Собрать карточку» AGAIN on a query the model just refused.
         *
         * A person tapping the same button a second time IS the retry, and it outranks a stored
         * «это не слово»: the verdict they are disputing is the one thing on screen they can see is
         * wrong. So an explicit re-tap ignores the negative row outright — not only a stale one —
         * and buys a fresh call, while the 24-hour expiry
         * ({@see \App\Modules\Generation\Domain\Service\NegativeVerdictLifetime}) goes on governing
         * every AUTOMATIC path, where nobody is watching and nobody asked (решение архитектора, 25.08).
         *
         * It does NOT bypass the daily cap: that is a spend guard, not a verdict, and a retry loop
         * is exactly what it exists to stop. Nor does it re-buy a POSITIVE card — there is nothing
         * to dispute about a card that exists.
         */
        public bool $retry = false,
    ) {}
}
