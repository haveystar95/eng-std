<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Dto;

/** One item of a draft: target-language text + source-language translation, from the model. */
final readonly class GeneratedItem
{
    public function __construct(
        public string $text,          // target language (e.g. English)
        public string $type,          // 'word' | 'phrase'
        public string $translation,   // source language (e.g. Russian)
        public ?string $example,
        public ?string $cefr,
        public ?string $transcription = null,      // IPA of the target-language text
        public ?string $exampleTranslation = null, // example rendered in the source language
    ) {}
}
