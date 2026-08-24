<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Application\Dto;

/** The audit's whole answer: what was judged, in which languages, and what a human should read. */
final readonly class TranslationKeyAuditView
{
    /**
     * @param  int  $seen  pairs judged across every language swept, terms and examples together
     * @param  list<TranslationKeyCandidate>  $candidates
     * @param  list<string>  $groupNames  every group the rule knows, so a report can show empty ones
     * @param  array<string, int>  $seenTermsByLang  language => term pairs judged in it
     * @param  array<string, int>  $seenExamplesByLang  language => example pairs judged in it
     * There is deliberately no «skipped» count any more. There used to be one, because the example
     * gloss recorded no language and a term translated into two of them made its example's language
     * a guess — the sweep skipped those pairs and reported how many. `example_translations` names
     * the language on the row, so the class of unjudgeable pairs is empty by construction, and a
     * counter that can only ever say zero is a claim about the past.
     *
     * @param  list<string>  $ruleLanguages  the languages the rule HAS counterpart lists for.
     *                       Reported so a sweep can tell «clean» apart from «the rule is silent
     *                       here»: a language it was never taught yields zero candidates either way,
     *                       and only this list says which of the two happened.
     */
    public function __construct(
        public int $seen,
        public array $candidates,
        public array $groupNames,
        public array $seenTermsByLang,
        public array $seenExamplesByLang,
        public array $ruleLanguages,
    ) {}
}
