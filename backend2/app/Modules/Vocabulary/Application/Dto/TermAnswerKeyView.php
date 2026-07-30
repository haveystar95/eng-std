<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Application\Dto;

/**
 * The set of correct TARGET-language forms for a term — the answer key the server grades a
 * typed/assembled answer against. Always a set (one entry today, term text; alternative forms
 * like colour/color are added later without changing this shape). Translations are the prompt
 * side and never belong here.
 */
final readonly class TermAnswerKeyView
{
    /** @param list<string> $accepted non-empty set of accepted target forms */
    public function __construct(
        public string $termId,
        public array $accepted,
        public bool $isPhrase,
    ) {}
}
