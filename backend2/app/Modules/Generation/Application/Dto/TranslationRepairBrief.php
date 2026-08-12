<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Dto;

use App\Modules\Shared\Domain\ValueObject\LanguageCode;

/**
 * Re-translate THIS item — the narrowest possible ask.
 *
 * The target-language side (`text`, `sentence`) is given and must come back untouched: it is
 * already correct, it is what the learner is being taught, and on an existing term it is what the
 * stored distractors were built against. Only the learner-language rendering is being redone, which
 * is why this is a separate port from the collection generator (which invents items) and from the
 * example regenerator (which replaces the sentence).
 */
final readonly class TranslationRepairBrief
{
    public function __construct(
        public string $text,              // the target-language term, verbatim
        public string $type,              // word | phrase | idiom | phrasal_verb
        public ?string $sentence,         // the example to translate, or null when there is none
        public LanguageCode $targetLang,  // the language `text`/`sentence` are in
        public LanguageCode $sourceLang,  // the language the translations must be in
    ) {}
}
