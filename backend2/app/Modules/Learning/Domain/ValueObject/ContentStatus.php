<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\ValueObject;

/**
 * Whether a trainer's card can be BUILT from one term's own content.
 *
 * Three values and not two, because "нет" has two different causes and only one of them is the
 * term's fault:
 *
 *  * `ok`             — this term's data is enough; the card assembles.
 *  * `blocked`        — this term's data is NOT enough, and no amount of other words helps. A
 *                       single word cannot be assembled from chips, a term with no example has no
 *                       sentence to blank. {@see ContentGap} says which.
 *  * `pool_dependent` — the term's own data was never the question. `multiple_choice` builds its
 *                       wrong options out of OTHER words (the session's neighbours at the
 *                       recognition rungs, the distractor reader's pool above them), so whether the
 *                       card assembles is a fact about the session, not about the term. Reporting
 *                       it as `ok` would promise a card that a one-word collection cannot deal;
 *                       reporting it as `blocked` would send someone to the станок for content
 *                       that would change nothing.
 */
enum ContentStatus: string
{
    case Ok = 'ok';
    case Blocked = 'blocked';
    case PoolDependent = 'pool_dependent';
}
