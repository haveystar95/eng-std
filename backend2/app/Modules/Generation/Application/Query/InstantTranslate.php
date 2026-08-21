<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Query;

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
    ) {}
}
