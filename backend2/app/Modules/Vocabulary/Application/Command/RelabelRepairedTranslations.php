<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Application\Command;

/**
 * Fix the LABEL on translation rows whose TEXT the retrospective language repair already rewrote.
 *
 * Dry by default: `$apply = false` reports what would change and touches nothing.
 */
final readonly class RelabelRepairedTranslations
{
    public function __construct(
        public string $lang = 'ru',
        public bool $apply = false,
    ) {}
}
