<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Dto;

use App\Modules\Shared\Domain\ValueObject\LanguageCode;

/** What the model is asked about one word the database does not have. */
final readonly class WordLookupBrief
{
    public function __construct(
        public string $query,
        /** The language being learned — what `text`, `description` and `example` come back in. */
        public LanguageCode $targetLang,
        /** The learner's language — what `translation` and `example_translation` come back in. */
        public LanguageCode $nativeLang,
    ) {}
}
