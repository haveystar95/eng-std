<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto;

final readonly class SceneView
{
    /**
     * @param  list<string>  $goalsNative
     * @param  array{url: string, author: string|null, author_url: string|null}|null  $image
     */
    public function __construct(
        public string $id,
        public int $order,
        public string $kind,
        public int $priority,
        public string $titleNative,
        public string $titleTarget,
        public string $teachesNative,
        public array $goalsNative,
        public string $learnerRoleTarget,
        public string $learnerRoleNative,
        public string $partnerRoleTarget,
        public string $partnerRoleNative,
        public ?array $image,
        public string $lessonStatus,
        public ?string $lessonFailReason,
        public ?int $dayNumber,
        public ?string $costUsd,
        public ?int $latencyMs,
        public ?string $promptVersion,
    ) {}
}
