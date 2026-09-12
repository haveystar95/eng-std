<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Service;

use App\Modules\Plan\Domain\Entity\PlanEvent;
use App\Modules\Plan\Domain\Repository\PlanEventRepository;
use App\Modules\Plan\Domain\ValueObject\PlanDayId;
use App\Modules\Plan\Domain\ValueObject\PlanEventId;
use App\Modules\Plan\Domain\ValueObject\PlanEventKind;
use App\Modules\Plan\Domain\ValueObject\PlanId;
use App\Modules\Shared\Domain\Service\Clock;
use App\Modules\Shared\Domain\ValueObject\UserId;

/**
 * Writes one line into the plan's journal. Called INSIDE the transaction of the handler whose
 * change the line records, so the fact and the change commit together; the letter the line may
 * become is dispatched after the commit ({@see PlanNotifier}).
 */
final readonly class PlanEventJournal
{
    public function __construct(
        private PlanEventRepository $events,
        private Clock $clock,
    ) {}

    /**
     * @param  array<string, int|string|null>  $payload
     * @return PlanEvent|null the line, or null when the journal already holds it (a repeated fact)
     */
    public function record(
        PlanId $planId,
        UserId $userId,
        PlanEventKind $kind,
        ?PlanDayId $dayId = null,
        ?int $dayNumber = null,
        array $payload = [],
    ): ?PlanEvent {
        $event = PlanEvent::record(PlanEventId::generate(), $userId, $planId, $kind, $this->clock->now(), $dayId, $dayNumber, $payload);

        return $this->events->append($event) ? $event : null;
    }
}
