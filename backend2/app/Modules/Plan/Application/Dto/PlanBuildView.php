<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto;

/** What the client polls after creating a plan: is it built, unclear, failed — and what it cost. */
final readonly class PlanBuildView
{
    public function __construct(
        public string $id,
        public string $status,
        public ?string $unclearReason,
        public ?string $failReason,
        public int $scenesCount,
        public ?string $costUsd,
        public ?int $latencyMs,
        public ?int $attempts,
        public VersionsView $versions,
    ) {}
}
