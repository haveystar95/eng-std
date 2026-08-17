<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Dto;

/**
 * What one pass of the language cleanup found and did — one line per offending row, so the console
 * can print a table and a dry run and a real run produce the same list.
 *
 * @phpstan-type Action 'dropped'|'retranslated'|'unfixable'|'planned'
 */
final readonly class LanguageRepairReport
{
    public function __construct(
        public string $termId,
        public string $termText,
        public string $field,
        public string $before,
        public ?string $after,      // null when nothing was written
        public string $action,      // dropped | retranslated | unfixable | planned
        public string $note,
        public int $attempts = 0,
    ) {}
}
