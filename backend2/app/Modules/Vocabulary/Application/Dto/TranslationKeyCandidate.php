<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Application\Dto;

/** One key that has stopped pointing at its own source — a term's or an example's — and what it dropped. */
final readonly class TranslationKeyCandidate
{
    /**
     * The rule's finding arrives here FLAT — words and forms, no Domain object.
     *
     * The report that renders this is assembled in Generation (it needs Collections' deck titles
     * too), and Generation may read Vocabulary's Application, never its Domain. Handing over
     * `AddresseeGap` would have been the shorter route and a boundary deptrac does catch; flattening
     * once, here, keeps the rule's own type where the rule lives.
     *
     * @param  'term'|'example'  $kind  which of the card's two keys this is
     * @param  string  $sourceText  the string the learner must reproduce: the term, or the sentence
     * @param  string  $lang  the translation's language — the sweep judges every language the store has
     * @param  list<string>  $groups  the groups the pair tripped
     * @param  list<string>  $missingWords  the source's own words left unanswered, in source order
     * @param  array<string, list<string>>  $expectedForms  missing word => forms that would have cleared it
     */
    public function __construct(
        public string $termId,
        public string $termText,
        public string $kind,
        public string $sourceText,
        public string $lang,
        public string $translation,
        public array $groups,
        public array $missingWords,
        public array $expectedForms,
    ) {}
}
