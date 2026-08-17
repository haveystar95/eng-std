<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Command;

use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use App\Modules\Shared\Domain\ValueObject\TermId;

/** Enrich a bare user term: LLM fills translation/transcription/example, then a Pexels photo. */
final readonly class EnrichTerm
{
    public function __construct(
        public TermId $termId,
        public LanguageCode $translationLang,
    ) {}
}
