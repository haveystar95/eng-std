<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Dto;

/** The audit's whole answer: what was judged, what to read, and what the rule can and cannot say. */
final readonly class TranslationKeyAuditView
{
    /**
     * @param  int  $seen  pairs judged across every language swept, terms and examples together
     * @param  list<TranslationKeyAuditRow>  $rows
     * @param  list<string>  $groupNames  every group, so a report can show the empty ones too
     * @param  array<string, int>  $seenTermsByLang  language => term pairs judged in it
     * @param  array<string, int>  $seenExamplesByLang  language => example pairs judged in it
     * @param  list<string>  $ruleLanguages  languages the rule has counterpart lists for, so the
     *                       report can mark a silent language as silent rather than clean
     */
    public function __construct(
        public int $seen,
        public array $rows,
        public array $groupNames,
        public array $seenTermsByLang,
        public array $seenExamplesByLang,
        public array $ruleLanguages,
    ) {}
}
