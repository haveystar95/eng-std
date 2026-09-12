<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto;

use App\Modules\Plan\Domain\ValueObject\NotificationKind;

/**
 * A letter: ids and the alert text, nothing else (mobile-sync-contract, «Push»). The client opens
 * the plan through the normal API; the push never carries the plan's content.
 */
final readonly class PushMessage
{
    public function __construct(
        public string $userId,
        public NotificationKind $kind,
        public string $title,
        public string $body,
        public string $planId,
        public ?int $dayNumber,
    ) {}

    /**
     * The custom keys next to `aps`.
     *
     * @return array{kind: string, plan_id: string, day_number: int|null}
     */
    public function ids(): array
    {
        return ['kind' => $this->kind->value, 'plan_id' => $this->planId, 'day_number' => $this->dayNumber];
    }
}
