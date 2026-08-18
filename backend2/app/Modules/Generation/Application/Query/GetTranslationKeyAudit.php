<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Query;

/** The translation-key audit, with the decks each candidate is asked in. */
final readonly class GetTranslationKeyAudit
{
    public function __construct(
        public string $termLang = 'en',
        public string $sourceLang = 'ru',
    ) {}
}
