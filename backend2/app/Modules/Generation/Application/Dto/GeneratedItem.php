<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Dto;

/** One item of a draft: target-language text + source-language translation, from the model. */
final readonly class GeneratedItem
{
    public function __construct(
        public string $text,          // target language (e.g. English)
        public string $type,          // 'word' | 'phrase' | 'idiom' | 'phrasal_verb'
        public string $translation,   // source language (e.g. Russian)
        public ?string $example,
        public ?string $cefr,
        public ?string $transcription = null,      // IPA of the target-language text
        public ?string $exampleTranslation = null, // example rendered in the source language
        public ?string $imageApiPrompt = null,     // model's short image-search query for this term (v4+)
        /**
         * The three per-pair products the CORE started producing at prompt v15, all of them
         * unvalidated here — this DTO is what the model said, and the validator is what decides.
         *
         * @var list<string>  0–3 near-synonyms of `text`, in the TARGET language
         */
        public array $synonyms = [],
        /** @var list<string>  0–2 further readings in the SOURCE language, beside `translation` */
        public array $otherTranslations = [],
        /** How `text` reads, spelled in the SOURCE language's letters. Null = the model said "". */
        public ?string $transliteration = null,
    ) {}
}
