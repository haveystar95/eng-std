<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Application\Dto;

/**
 * What the "New example" action needs about a term: its text and language, the example currently
 * shown (to avoid), the language its translations are in (so the new example is translated too),
 * and WHAT THE CARD IS — a line is the sentence being learned and may not be given a second one
 * around it ({@see \App\Modules\Generation\Domain\Service\ExampleAdmission}).
 */
final readonly class ExampleRegenContext
{
    public function __construct(
        public string $text,
        public string $lang,
        public ?string $currentExample,
        public ?string $translationLang,
        /** `terms.kind` — `line`, `word`, `chunk`, `number`, or null on everything outside a plan. */
        public ?string $kind = null,
    ) {}
}
