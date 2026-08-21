<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Command;

/**
 * Run the enrichment станок over a chunk of terms. Ids are plain strings, not TermId, because this
 * command crosses a queue boundary — the job serialises exactly this list.
 */
final readonly class BuildTermEnrichments
{
    /**
     * @param  list<string>  $termIds
     * @param  string  $translationLang  the collection's language — which of each term's translations
     *        the станок shows the model. A plain string for the same reason the ids are: it crosses
     *        a queue boundary.
     * @param  bool  $ignoreVersionMark  the caller already chose these terms for a reason the version
     *        mark cannot express — the TOP-UP: «this example is short of distractors», asked of
     *        coverage, not of the journal. Without this the handler re-filters the list by the mark
     *        and a догон over terms already processed at the current version does nothing at all,
     *        which is exactly what a догон is for: a proofreader deleted rows, or the rules got
     *        better, and the term needs re-running at the SAME version it already carries.
     */
    public function __construct(
        public array $termIds,
        public string $generatorVersion,
        public string $translationLang = 'ru',
        public bool $ignoreVersionMark = false,
    ) {}
}
