<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Dto;

/**
 * A counted label — a suppression source (`review` / `audit`) or the field a generation rejection
 * was about. One shape for both, because both are «сколько строк с таким ярлыком» and a second DTO
 * would only differ in the name of its first property.
 */
final readonly class ContentLabelCount
{
    public function __construct(
        public string $label,
        public int $count,
    ) {}
}
