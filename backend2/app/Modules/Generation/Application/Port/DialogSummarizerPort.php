<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Port;

use App\Modules\Generation\Application\Dto\DialogSummaryBrief;
use App\Modules\Generation\Application\Dto\DialogSummaryResult;

/**
 * One cheap text call that turns a finished dialog's transcript into a short native-language
 * recap (what went well, one or two main mistakes). Behind a port so tests use a deterministic fake.
 */
interface DialogSummarizerPort
{
    public function summarize(DialogSummaryBrief $brief): DialogSummaryResult;
}
