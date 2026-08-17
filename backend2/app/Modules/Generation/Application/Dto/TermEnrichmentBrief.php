<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Dto;

use App\Modules\Shared\Domain\ValueObject\LanguageCode;

/**
 * What the enricher needs to fill in a bare user term: the term text and its language, plus the
 * language to translate into. The term text is always passed as data, never as instructions.
 */
final readonly class TermEnrichmentBrief
{
    public function __construct(
        public string $text,
        public string $type,               // word | phrase | idiom | phrasal_verb
        public LanguageCode $termLang,     // the language the term is in (what's being learned)
        public LanguageCode $translationLang,
    ) {}
}
