<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Dto;

/**
 * One candidate for proof-reading: the defective key, and the decks that ask it.
 *
 * Two modules' facts joined in one row — Vocabulary judged the key, Collections said where it is
 * used — which is why this DTO lives here and not in either of them.
 */
final readonly class TranslationKeyAuditRow
{
    /**
     * @param  list<string>  $groups       the addressee groups the pair tripped
     * @param  list<string>  $collections  titles of the live decks this term sits in
     */
    public function __construct(
        public string $termId,
        public string $termText,
        public string $translation,
        public array $groups,
        public array $collections,
    ) {}
}
