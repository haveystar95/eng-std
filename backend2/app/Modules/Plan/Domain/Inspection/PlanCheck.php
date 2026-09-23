<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Inspection;

/**
 * One automatic check of the admin's plan page (наряд ADM-1, «Что не так») — a small pure rule over the plan's facts.
 * The canon each check holds is written in its own class and tested as canon, not as the code happens to be.
 */
interface PlanCheck
{
    /** The check's stable code — what a finding is filed under. */
    public function code(): string;

    /** @return list<PlanIssue> */
    public function find(PlanFacts $facts): array;
}
