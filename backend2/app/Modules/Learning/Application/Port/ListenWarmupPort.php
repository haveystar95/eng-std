<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Port;

use App\Modules\Learning\Application\Dto\ListenLineView;
use App\Modules\Learning\Application\Dto\ListenWarmupBrief;

/**
 * P-Listen, as far as Learning is concerned: a goal in, three lines out — OR NOTHING.
 *
 * Declared here and fulfilled in Generation, the same direction as {@see PlanOutlinePort}.
 *
 * ## It never throws, and that is the contract
 *
 * The listening step is OPTIONAL from the product's side (кадр V4·03: «Послушать» and «Пропустить»
 * are the same height), which makes it optional from the machine's side too: a vendor outage, a
 * malformed answer or an empty list all mean the same thing to the entry — the step is not offered
 * and the flow goes to the date. So the failure of a call nobody asked for is expressed as an empty
 * list rather than as an exception, and there is no screen anywhere that says the warm-up broke.
 *
 * The alternative was tried in the head and rejected: an exception would have to be caught by the
 * controller, turned back into an empty list, and the only thing the round trip would add is a
 * chance for somebody to forget the catch and put a 500 in front of a step the learner may skip.
 */
interface ListenWarmupPort
{
    /** @return list<ListenLineView> empty = the step is not offered at all */
    public function linesFor(ListenWarmupBrief $brief): array;
}
