<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Dto;

use App\Modules\Shared\Domain\ValueObject\LanguageCode;

/** What the regenerator needs: the term, the example to avoid, and the language to translate into. */
final readonly class ExampleRegenBrief
{
    public function __construct(
        public string $text,
        public LanguageCode $termLang,
        public LanguageCode $translationLang,
        public ?string $avoid,   // the current example, so the model returns a different one
    ) {}
}
