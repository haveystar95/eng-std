<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Service;

use App\Modules\Plan\Domain\Entity\PlanEvent;
use App\Modules\Plan\Domain\Repository\PlanRepository;
use App\Modules\Plan\Domain\ValueObject\PlanEventKind;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;
use App\Modules\Plan\Domain\ValueObject\PlanId;
use App\Modules\Shared\Domain\Service\Clock;
use App\Modules\Shared\Domain\Service\TransactionManager;

/**
 * «ДЕНЬ СОБРАН» IS SAID WHEN THE DAY HAS ITS PICTURES (DAY-UI-3).
 *
 * The scene goes `illustrating` → `ready` in one conditional write — its build over at that moment (`built_at`, наряд
 * FIX-4 §6) — and in the same transaction the `day_ready` journal line is written for the day the scene stands on — read
 * again inside the transaction, because a reschedule may have moved the scene (or dropped it: no day, no line). The
 * letter follows the commit. A second caller (the photo job's failure path after the job itself
 * finished) finds the scene ready and writes nothing.
 */
final readonly class SceneReadiness
{
    public function __construct(
        private PlanRepository $plans,
        private TransactionManager $tx,
        private PlanEventJournal $journal,
        private PlanNotifier $notifier,
        private Clock $clock,
    ) {}

    public function markReady(PlanId $planId, PlanSceneId $sceneId): void
    {
        /** @var PlanEvent|null $ready */
        $ready = null;
        $this->tx->run(function () use ($planId, $sceneId, &$ready): void {
            if (! $this->plans->finishIllustration($sceneId, $this->clock->now())) {
                return;
            }
            $current = $this->plans->findById($planId);
            foreach ($current?->days() ?? [] as $day) {
                if ($current !== null && $day->sceneId()?->equals($sceneId) === true) {
                    $ready = $this->journal->record(
                        $current->id(), $current->userId(), PlanEventKind::DayReady,
                        $day->id(), $day->number(), ['scene_id' => $sceneId->value],
                    );
                    break;
                }
            }
        });

        $this->notifier->notify($ready);
    }
}
