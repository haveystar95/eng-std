<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Query;

/** The plan's journal of calls (наряд ADM-1, «Вызовы API») — newest first, a page at a time, by day and source. */
final readonly class GetPlanCalls
{
    public const SOURCES = ['model', 'voice', 'judge', 'client'];

    public function __construct(
        public string $code,
        public ?int $day,
        public ?string $source,
        public ?string $cursor,
        public int $limit,
    ) {}
}
