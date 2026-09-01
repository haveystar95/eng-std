<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Command;

/**
 * Take out of the study pool the words of every plan that has already ended.
 *
 * `apply = false` computes the whole answer and writes nothing — the default, because this runs
 * across every account on the only copy of the data that exists.
 */
final readonly class ArchiveEndedPlanTerms
{
    public function __construct(public bool $apply = false) {}
}
