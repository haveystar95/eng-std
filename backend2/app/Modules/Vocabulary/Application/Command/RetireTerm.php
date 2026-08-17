<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Application\Command;

use App\Modules\Shared\Domain\ValueObject\TermId;

/** Retire a term from the global dictionary — out of every collection, for every learner. */
final readonly class RetireTerm
{
    public function __construct(public TermId $termId) {}
}
