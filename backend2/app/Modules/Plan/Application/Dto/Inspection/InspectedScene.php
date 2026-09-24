<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto\Inspection;

use DateTimeImmutable;

/**
 * A scene row as stored, for the admin's plan page (наряд ADM-1) — the columns the aggregate does not carry out (the
 * build stamp, the moment the row last changed, the raw findings). Read-only; nothing is derived here.
 */
final readonly class InspectedScene
{
    /** @param list<array<string, mixed>> $findings `checks_json` as stored: `{code, address, detail}` */
    public function __construct(
        public string $id,
        public int $order,
        public string $kind,
        public int $priority,
        public string $titleNative,
        public string $titleTarget,
        public string $learnerRoleNative,
        public string $learnerRoleTarget,
        public string $partnerRoleNative,
        public string $partnerRoleTarget,
        public string $lessonStatus,
        public ?string $promptVersion,
        public ?string $buildVersion,
        public ?string $model,
        public ?string $costUsd,
        public ?int $latencyMs,
        public ?int $attempts,
        public array $findings,
        public ?string $failReason,
        public ?DateTimeImmutable $buildStartedAt,
        public ?DateTimeImmutable $generatedAt,
        public ?DateTimeImmutable $updatedAt,
        public ?string $imageUrl,
        public ?string $imageAuthor,
        public ?string $imageTone,
        public ?string $partnerVoiceGender,
        /** When the build ENDED — the scene went ready, its photos in (`built_at`, наряд FIX-4 §6); `generatedAt` is its start. */
        public ?DateTimeImmutable $builtAt = null,
    ) {}
}
