<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\ValueObject;

/**
 * ОДНО СЛОВО О ДНЕ — «не начат» · «идёт» · «материал пройден» · «пройден» (наряд DAY-FIX-2, Ч.3;
 * наряд DAY-FIX-3, Ч.4.3).
 *
 * Four words and no more, on the wire as codes the client localises. Distinct from
 * {@see PlanDayStatus}, which is about GENERATION (queued, being written, ready, failed, done) and
 * says nothing about whether the learner has sat down. A day that is `ready` may be untouched or
 * half-walked, and the plan tab used to guess which by counting on its own.
 *
 * «Материал пройден» is the seam between the day's two sittings ({@see \App\Modules\Learning\Domain\Service\PlanSittings}):
 * every card of «Материал» has been met and exercised, and the conversation is still ahead. It is
 * what lets the plan tab say «материал пройден · разговор около 6 минут» and put «К разговору» on
 * the button instead of «Продолжить».
 */
enum PlanDayState: string
{
    case NotStarted = 'not_started';

    case InProgress = 'in_progress';

    case MaterialDone = 'material_done';

    case Done = 'done';
}
