<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto;

use App\Modules\Plan\Domain\ValueObject\DeliveryStatus;

final readonly class DeliveryResult
{
    public function __construct(
        public DeliveryStatus $status,
        public ?string $reason = null,
    ) {}
}
