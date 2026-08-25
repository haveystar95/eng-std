<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Application\Port;

use App\Modules\Shared\Domain\ValueObject\TermId;

/**
 * Writes a term's reading hint for one SUPPORT language — «cómo estás» → «комо эстас».
 *
 * Write-once per (term, support language), the same rule and the same reason as
 * {@see TermDescriptionWriter}: a term is globally deduplicated, so the second collection to pull
 * the same word in arrives at a row that already has a hint, and replacing it would churn content
 * nobody asked to change. A curator's correction goes through a different door.
 */
interface TermTransliterationWriter
{
    /**
     * Store the hint if this (term, support language) has none. Returns whether it wrote.
     *
     * @param  string  $lang  the SUPPORT language whose alphabet the hint is written in — never the
     *        term's own. The caller has already had it judged by
     *        {@see \App\Modules\Generation\Domain\Service\EnrichmentValidator::transliterationFor()};
     *        this writes what survived.
     */
    public function ensure(
        TermId $termId,
        string $lang,
        string $text,
        string $source = 'auto',
        ?string $generatorVersion = null,
    ): bool;
}
