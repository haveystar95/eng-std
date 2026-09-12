<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto;

use App\Modules\Plan\Domain\ValueObject\DeliveryStatus;
use App\Modules\Plan\Domain\ValueObject\NotificationKind;

/** One line of the delivery log (`plan_notifications`) — append-only. */
final readonly class NotificationRecord
{
    public function __construct(
        public string $id,
        public string $userId,
        public string $planId,
        public ?string $eventId,
        public NotificationKind $kind,
        public ?int $dayNumber,
        public string $localDate,
        public DeliveryStatus $status,
        public ?string $reason,
    ) {}
}
