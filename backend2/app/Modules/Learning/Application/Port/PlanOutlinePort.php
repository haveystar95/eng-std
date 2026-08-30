<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Port;

use App\Modules\Learning\Application\Dto\PlanModelAnswer;
use App\Modules\Learning\Application\Dto\PlanOutlineBrief;

/**
 * P1, as far as Learning is concerned: a brief in, a validated skeleton out.
 *
 * The port is declared HERE and fulfilled in Generation — the same direction as Vocabulary's
 * {@see \App\Modules\Vocabulary\Application\Port\DispatchesTermEnrichment}. Learning owns the plan
 * and must be able to ask for its skeleton; Generation owns everything about talking to a model —
 * the prompt files, the cost table, the request log, the retry policy — and none of that belongs in
 * a module about scheduling.
 *
 * SYNCHRONOUS, unlike collection generation, and that is a product decision rather than an
 * oversight. The skeleton is the screen the learner reads BEFORE they commit: they typed a goal and
 * are waiting on it, one call takes about ten seconds, and a «мы вам сообщим» on the first screen
 * of a feature is how the feature does not get used. The DAYS are the asynchronous part, because
 * they are what the learner walks away from.
 */
interface PlanOutlinePort
{
    /**
     * @throws \App\Modules\Learning\Application\Exception\PlanOutlineRefused when the model failed
     *         or its answer did not survive the validator
     */
    public function outlineFor(PlanOutlineBrief $brief): PlanModelAnswer;
}
