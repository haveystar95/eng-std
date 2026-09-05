<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\ValueObject;

/**
 * ОДНО СЛОВО О ДНЕ — «не начат» · «идёт» · «пройден» (наряд DAY-FIX-2, Ч.3).
 *
 * Three words and no more, on the wire as codes the client localises. Distinct from
 * {@see PlanDayStatus}, which is about GENERATION (queued, being written, ready, failed, done) and
 * says nothing about whether the learner has sat down. A day that is `ready` may be untouched or
 * half-walked, and the plan tab used to guess which by counting on its own.
 */
enum PlanDayState: string
{
    case NotStarted = 'not_started';

    case InProgress = 'in_progress';

    case Done = 'done';
}
