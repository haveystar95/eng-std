<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto;

/** Which server build and which prompt files are answering. */
final readonly class VersionsView
{
    public function __construct(
        public string $build,
        public string $promptPlan,
        public string $promptLesson,
    ) {}
}
