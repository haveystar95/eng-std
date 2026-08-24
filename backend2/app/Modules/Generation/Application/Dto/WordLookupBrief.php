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
        /**
         * The translation the learner was SHOWN in the translator line and confirmed by pressing
         * «Собрать карточку» — DeepL's, our catalogue's, whichever rung answered.
         *
         * Given, it is a decision and not an opinion: the model is told to return it unchanged and
         * to build the rest of the card around that reading, and the adapter writes it into the
         * result regardless of what came back. That is the whole of «перевод не переигрывается» on
         * this path — the learner read a line, agreed with it, and the card must not then say
         * something else.
         *
         * Null means the client did not say, which is every client until SYN-1b ships; the model
         * chooses, exactly as it did before.
         */
        public ?string $fixedTranslation = null,
    ) {}
}
