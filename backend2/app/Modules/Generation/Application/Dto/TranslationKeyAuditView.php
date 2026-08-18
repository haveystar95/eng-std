<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Dto;

/** The audit's whole answer: what was judged, what to read, and every group the rule knows. */
final readonly class TranslationKeyAuditView
{
    /**
     * @param  list<TranslationKeyAuditRow>  $rows
     * @param  list<string>  $groupNames  every group, so a report can show the empty ones too
     */
    public function __construct(
        public int $seen,
        public array $rows,
        public array $groupNames,
    ) {}
}
