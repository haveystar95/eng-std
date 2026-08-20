<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Application\Port;

use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Vocabulary\Domain\ValueObject\Provenance;

/**
 * REPLACES a term's core — its key and the fields around it — with freshly generated content.
 *
 * Every other writer in this module is additive: `ImportTerm` merges, `ensureIpa` fills only what is
 * empty, the enrichment writer appends and ignores duplicates. That rule exists because content is
 * expensive and a merge can never destroy a human's correction by accident.
 *
 * This is the deliberate exception, and it has exactly one caller: the showcase regeneration, whose
 * entire purpose is to replace content that a defect sweep has judged bad. Additive semantics there
 * would produce a term carrying both the old broken key and the new one, and the card would show
 * whichever the reader's ordering happened to pick — the worst of both. So: replace, stamp what
 * replaced it, and touch the term so the phone hears about it.
 *
 * What it does NOT touch, ever: `user_term_progress` and `reviews`. Regenerating the words is not a
 * statement about who has learned them, and the review log is append-only history.
 */
interface TermCoreWriter
{
    /**
     * @param  string  $translation  the new primary translation IN $translationLang
     * @param  string|null  $ipa  null leaves the stored value alone; a value replaces it
     * @param  string|null  $cefr  as above
     * @param  string|null  $imageApiPrompt  as above — an already-attached photo is NOT re-fetched,
     *         the query is stored for whoever asks next
     * @param  Provenance  $provenance  which prompt version and model wrote this core. Required, not
     *         optional: a row this writer touched can never honestly be un-stamped.
     * @return bool  false when there is no such live term
     */
    public function replaceCore(
        TermId $termId,
        string $translation,
        string $translationLang,
        ?string $ipa,
        ?string $cefr,
        ?string $imageApiPrompt,
        Provenance $provenance,
    ): bool;
}
