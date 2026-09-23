<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Query;

use App\Modules\Plan\Application\Inspection\PlanInspection;

/** Asks the plan's owner for one of its sounds; nothing else of the disk is reachable through it. */
final readonly class GetPlanAudioHandler
{
    public function __construct(
        private PlanInspection $plans,
        private GetPlanPageHandler $pages,
    ) {}

    /**
     * @return array{bytes: string, format: string}|null
     *
     * @throws PlanCodeConflict
     */
    public function __invoke(GetPlanAudio $query): ?array
    {
        $planId = $this->pages->resolve($query->code);

        return $planId === null ? null : $this->plans->audio($planId, $query->audioId);
    }
}
