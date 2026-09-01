<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Dto;

/**
 * What the ended-plan sweep found, and what it wrote.
 *
 * `rows` is per PLAN and `pairs` is DISTINCT per learner: a word standing in two ended plans of one
 * account is listed under both and leaves the pool once. `affected` is what the write actually
 * touched — zero on a dry run.
 */
final readonly class PlanArchiveSweepView
{
    /** @param list<array{user_id: string, title: string, status: string, pairs: int}> $rows */
    public function __construct(
        public array $rows,
        public int $pairs,
        public int $affected,
    ) {}
}
