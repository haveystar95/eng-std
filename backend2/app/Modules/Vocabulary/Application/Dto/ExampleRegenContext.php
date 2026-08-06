<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Application\Dto;

/**
 * What the "New example" action needs about a term: its text and language, the example currently
 * shown (to avoid), and the language its translations are in (so the new example is translated too).
 */
final readonly class ExampleRegenContext
{
    public function __construct(
        public string $text,
        public string $lang,
        public ?string $currentExample,
        public ?string $translationLang,
    ) {}
}
