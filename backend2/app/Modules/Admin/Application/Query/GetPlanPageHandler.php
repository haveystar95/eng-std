<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Query;

use App\Modules\Admin\Application\Port\AdminUserReader;
use App\Modules\Plan\Application\Inspection\PlanCodeAmbiguous;
use App\Modules\Plan\Application\Inspection\PlanInspection;

/**
 * The plan's page is put together by the module that owns the plan ({@see PlanInspection}); the panel only asks for it —
 * the same boundary as the ladder's rung asked of Learning. The one thing added here is the panel's own: who the learner
 * is (their card in the panel).
 */
final readonly class GetPlanPageHandler
{
    public function __construct(
        private PlanInspection $plans,
        private AdminUserReader $users,
    ) {}

    /**
     * @return array<string, mixed>|null null — no plan by that code
     *
     * @throws PlanCodeConflict when the code names two plans
     */
    public function __invoke(GetPlanPage $query): ?array
    {
        $planId = $this->resolve($query->code);
        if ($planId === null) {
            return null;
        }
        if ($query->section !== null) {
            return $this->plans->section($planId, $query->section, $query->day);
        }
        $header = $this->plans->header($planId);
        if ($header === null) {
            return null;
        }
        $user = $this->users->profile((string) $header['user_id']);

        return [...$header, 'user' => $user === null ? null : ['id' => $user->id, 'name' => $user->name, 'email' => $user->email]];
    }

    public function resolve(string $code): ?string
    {
        try {
            return $this->plans->resolve($code);
        } catch (PlanCodeAmbiguous $e) {
            throw new PlanCodeConflict($e->getMessage(), $e->ids);
        }
    }
}
