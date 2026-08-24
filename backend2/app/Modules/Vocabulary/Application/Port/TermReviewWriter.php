<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Application\Port;

/**
 * The edits a human proofreader makes to generated content. Separate from TermEnrichmentWriter, which
 * only ever APPENDS what a generator produced: this port is the other direction — a person removing a
 * bad row or correcting a wording.
 *
 * Everything is addressed by TEXT rather than by id, because a review is written against what a human
 * read, and ids are regenerated whenever the database is rebuilt. Each method reports how many rows it
 * touched so the caller can tell "applied" from "already applied" from "never matched" — a review that
 * silently matched nothing is the failure mode worth catching.
 */
interface TermReviewWriter
{
    /** Find a term by its exact text (the review's handle on it). Null when no such term. */
    public function findTermIdByText(string $text): ?string;

    /**
     * Drop a distractor of this term's pinned example. `$contains` matches a fragment instead of the
     * whole sentence, for review entries that quote only the offending part.
     *
     * `$source` ('review' or 'audit') is recorded alongside every deleted sentence as a suppression —
     * the reason a deletion outlives the row: the станок filters its candidates through that list
     * before validation, so a re-proposed sentence does not come back on the next топап.
     */
    public function removeDistractor(string $termId, string $sentence, bool $contains, string $source): int;

    /**
     * Record a sentence as suppressed WITHOUT touching `example_distractors` — for backfilling a
     * suppression from a sentence that was already removed before this table existed (an applied
     * review file, a past audit run). Returns 1 for a newly-written suppression, 0 when this
     * (term, sentence) pair was already suppressed.
     */
    public function suppressDistractor(string $termId, string $sentence, string $source): int;

    /**
     * Rewrite one distractor's span and correction, addressed by its sentence. The sentence itself is
     * never touched: a review that reaches for this has decided the wrong sentence is fine and the
     * EXPLANATION of it is wrong — «at → services» underlining the wrong word.
     */
    public function fixDistractor(string $termId, string $sentence, string $errorSpan, string $correction): int;

    public function removeVariant(string $termId, string $text): int;

    /** Rewrite a variant's note, addressed by the variant's text. The variant itself stays. */
    public function setVariantNote(string $termId, string $text, ?string $note): int;

    /** Replace the term's primary translation (the prompt side of every card). */
    public function setPrimaryTranslation(string $termId, string $text): int;

    /**
     * Replace the pinned example's translation IN $lang — used when the generator wrote it in the
     * wrong language.
     *
     * The language is named rather than implied: this method exists precisely because a gloss came
     * out in the wrong language, and "replace the translation" without saying which one is the
     * instruction that produced the mess.
     */
    public function setPinnedExampleTranslation(string $termId, string $text, string $lang): int;
}
