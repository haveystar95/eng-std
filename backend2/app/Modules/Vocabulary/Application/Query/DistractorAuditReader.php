<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Application\Query;

use App\Modules\Vocabulary\Application\Dto\DistractorAuditRow;

/**
 * Reads every stored distractor with its pinned example, for a retro-audit of content that was
 * written before the checks it would face today existed.
 *
 * Whole-table on purpose: an audit that only looked at one collection would leave the rest of the
 * database at whatever standard applied on the day it was generated, which is the state this reader
 * exists to end.
 */
interface DistractorAuditReader
{
    /** @return list<DistractorAuditRow>  ordered by example, then by id — a stable pass */
    public function all(): array;
}
