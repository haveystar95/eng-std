<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Command;

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
    ) {}
}
