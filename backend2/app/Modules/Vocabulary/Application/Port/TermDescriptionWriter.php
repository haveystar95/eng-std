<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Application\Port;

use App\Modules\Shared\Domain\ValueObject\TermId;

/**
 * Writes a term's description — what the word means, in the language being learned.
 *
 * Write-once per (term, language) by design: `ensure()` never overwrites. A term is globally
 * deduplicated, so the second learner to look up the same word arrives at a row that already has a
 * description, and silently replacing it would churn content nobody asked to change — and would do
 * it with a cheaper model's answer than the one that may have written the first.
 */
interface TermDescriptionWriter
{
    /**
     * Store the description if this (term, lang) has none. Returns whether it wrote.
     *
     * @param  string  $lang  the language the DESCRIPTION is written in (today always the term's own)
     */
    public function ensure(
        TermId $termId,
        string $lang,
        string $text,
        string $source = 'ai',
        ?string $promptVersion = null,
        ?string $generationModel = null,
    ): bool;
}
