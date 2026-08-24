<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Application\Dto;

use App\Modules\Shared\Domain\ValueObject\LanguageCode;

/** A usage example as supplied by another module — primitives + Shared VOs only. */
final readonly class ExampleInput
{
    /**
     * @param  LanguageCode|null  $translationLang  the language `$sentenceTranslation` is written
     *         in — the SUPPORT side of the caller's language pair. Mandatory in practice whenever
     *         there is a translation at all ({@see \App\Modules\Vocabulary\Domain\ValueObject\Example}
     *         refuses one without a language); nullable only so an example with no gloss stays a
     *         one-argument construction.
     */
    public function __construct(
        public string $sentence,
        public ?string $sentenceTranslation = null,
        public ?LanguageCode $translationLang = null,
    ) {}
}
