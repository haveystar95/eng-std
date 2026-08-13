<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Application\Dto;

/** One translation row that claims a language, together with what it needs to be judged. */
final readonly class RepairedTranslationRow
{
    public function __construct(
        public string $rowId,
        public string $termId,
        public string $termText,
        public string $declaredLang,
        public string $text,
        /** True when the row's text has been rewritten since it was inserted — see the handler. */
        public bool $rewrittenSinceCreation,
    ) {}
}
