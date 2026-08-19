<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Application\Query;

/** Judge every primary translation against {@see \App\Modules\Vocabulary\Domain\Service\AddresseeIsomorphism}. */
final readonly class AuditTranslationKeys
{
    /**
     * @param  string|null  $sourceLang  one learner language, or NULL for every language the store
     *                      actually has. Null is the default because the audit's question is about
     *                      the whole showcase: a per-language run answers it only for the language
     *                      someone remembered to pass, and the one nobody passed is exactly where
     *                      an unreadable key survives.
     */
    public function __construct(
        public string $termLang = 'en',
        public ?string $sourceLang = null,
    ) {}
}
