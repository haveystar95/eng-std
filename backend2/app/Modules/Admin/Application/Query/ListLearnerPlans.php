<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Query;

/** A learner's plans for the «Планы» tab of their card (наряд ADM-1), newest first, deleted ones included. */
final readonly class ListLearnerPlans
{
    public function __construct(public string $userId) {}
}
