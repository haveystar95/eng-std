<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Dto;

use DateTimeImmutable;

/** The ephemeral client secret handed to the app, plus when it expires and the model it's bound to. */
final readonly class RealtimeToken
{
    public function __construct(
        public string $value,
        public DateTimeImmutable $expiresAt,
        public string $model,
    ) {}
}
