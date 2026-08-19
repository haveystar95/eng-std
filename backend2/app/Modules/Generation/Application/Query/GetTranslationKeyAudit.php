<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Query;

/** The translation-key audit, with the decks each candidate is asked in. */
final readonly class GetTranslationKeyAudit
{
    /** @param string|null $sourceLang one learner language, or NULL to sweep every language the store has */
    public function __construct(
        public string $termLang = 'en',
        public ?string $sourceLang = null,
    ) {}
}
