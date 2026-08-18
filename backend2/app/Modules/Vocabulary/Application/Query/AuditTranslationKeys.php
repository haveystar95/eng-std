<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Application\Query;

/** Judge every primary translation against {@see \App\Modules\Vocabulary\Domain\Service\AddresseeIsomorphism}. */
final readonly class AuditTranslationKeys
{
    public function __construct(
        public string $termLang = 'en',
        public string $sourceLang = 'ru',
    ) {}
}
