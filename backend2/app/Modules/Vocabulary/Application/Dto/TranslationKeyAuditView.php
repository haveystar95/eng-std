<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Application\Dto;

/** The audit's whole answer: what was judged, and what a human should read. */
final readonly class TranslationKeyAuditView
{
    /**
     * @param  list<TranslationKeyCandidate>  $candidates
     * @param  list<string>  $groupNames  every group the rule knows, so a report can show empty ones
     */
    public function __construct(
        public int $seen,
        public array $candidates,
        public array $groupNames,
    ) {}
}
