<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Application\Query;

use App\Modules\Vocabulary\Application\Dto\ExampleRegenContext;
use App\Modules\Shared\Domain\ValueObject\TermId;

/** Reads a term's regenerate-example context, or null when the term doesn't exist. */
interface ExampleRegenContextReader
{
    public function find(TermId $termId): ?ExampleRegenContext;
}
