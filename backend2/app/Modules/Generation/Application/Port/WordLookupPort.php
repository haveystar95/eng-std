<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Port;

use App\Modules\Generation\Application\Dto\WordLookupBrief;
use App\Modules\Generation\Application\Dto\WordLookupResult;

/**
 * ONE cheap model call for ONE word the database does not have.
 *
 * Deliberately the smallest LLM port in the module: no batching, no retries, no top-up. The caller
 * is a person watching a spinner after typing a word, and every knob this port does not have is a
 * second they do not wait and a fraction of a cent they do not spend. It throws on anything that is
 * not a well-formed answer; whether that is worth reporting is the handler's business.
 */
interface WordLookupPort
{
    public function lookUp(WordLookupBrief $brief): WordLookupResult;
}
