<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Application\Query;

use App\Modules\Vocabulary\Application\Dto\DistractorAuditRow;

/**
 * Reads stored distractors with the pinned example each one claims to be a broken version of.
 *
 * Two access paths, one row shape. `all()` is whole-table on purpose: an audit that only looked at
 * one collection would leave the rest of the database at whatever standard applied on the day it was
 * generated, which is the state that reader exists to end. `forTerm()` asks the same question about
 * ONE term, for the moment its example is being replaced — the caller has to decide which of the
 * rows the new sentence has orphaned, and reading the whole table to answer that would be absurd.
 */
interface DistractorAuditReader
{
    /** @return list<DistractorAuditRow>  ordered by example, then by id — a stable pass */
    public function all(): array;

    /** @return list<DistractorAuditRow>  this term's pinned example only, ordered by id */
    public function forTerm(string $termId): array;
}
