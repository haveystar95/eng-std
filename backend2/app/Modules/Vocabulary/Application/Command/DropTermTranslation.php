<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Application\Command;

use App\Modules\Shared\Domain\ValueObject\TermId;

/**
 * Remove one translation row from a term — the repair for a translation that is in the wrong
 * language while a correct one already sits next to it. Global curation, like {@see CurateTerm}:
 * there is no actor, and the guard is the admin route or the console.
 */
final readonly class DropTermTranslation
{
    public function __construct(
        public TermId $termId,
        public string $translationId,
    ) {}
}
