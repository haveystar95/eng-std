<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Application\Port;

use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use App\Modules\Shared\Domain\ValueObject\TermId;

/**
 * Kicks off asynchronous enrichment of a bare user term (translation, transcription, example,
 * photo). Declared here because "this term needs filling in" is a Vocabulary need; the AI
 * implementation lives in Generation and is bound in. Vocabulary stays ignorant of the LLM.
 * `translationLang` is the language to translate into (the learner's native language).
 */
interface DispatchesTermEnrichment
{
    public function enrich(TermId $termId, LanguageCode $translationLang): void;
}
