<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Dto;

use DateTimeImmutable;

/** The start-dialog response: the ephemeral token, its expiry, the model, and the target words. */
final readonly class StartedDialogView
{
    /** @param list<TargetWordView> $targetWords */
    public function __construct(
        public string $dialogId,
        public string $realtimeToken,
        public DateTimeImmutable $expiresAt,
        public string $model,
        public array $targetWords,
        public int $durationSeconds,
    ) {}
}
