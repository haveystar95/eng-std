<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Dto;

/**
 * One candidate for proof-reading: the defective key, what it dropped, and the decks that ask it.
 *
 * Two modules' facts joined in one row — Vocabulary judged the key, Collections said where it is
 * used — which is why this DTO lives here and not in either of them.
 */
final readonly class TranslationKeyAuditRow
{
    /**
     * @param  string  $lang  the translation's language
     * @param  list<string>  $groups        the addressee groups the pair tripped
     * @param  list<string>  $missingWords  the term's own words the translation left unanswered
     * @param  array<string, list<string>>  $expectedForms  missing word => forms that would have cleared it
     * @param  list<string>  $collections   titles of the live decks this term sits in
     */
    public function __construct(
        public string $termId,
        public string $termText,
        public string $lang,
        public string $translation,
        public array $groups,
        public array $missingWords,
        public array $expectedForms,
        public array $collections,
    ) {}
}
