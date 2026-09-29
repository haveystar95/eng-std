<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto;

/**
 * ONE SCREEN LINE OF A PLAN, TO BE SHORTENED (`plan_line_repair.v1`, наряд GEN-4): which line it is (`title_native`,
 * `title_target`, `teaches_native`, `goals_native`, or the plan's `title_native`), the language it is written in by name,
 * the most characters it may have, and the line as the plan builder wrote it.
 */
final readonly class PlanLineRepairRequest
{
    public function __construct(
        public string $field,
        public string $language,
        public int $limit,
        public string $line,
    ) {}
}
