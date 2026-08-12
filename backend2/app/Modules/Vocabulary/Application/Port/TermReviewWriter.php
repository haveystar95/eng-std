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
     */
    public function removeDistractor(string $termId, string $sentence, bool $contains = false): int;

    public function removeVariant(string $termId, string $text): int;

    /** Replace the term's primary translation (the prompt side of every card). */
    public function setPrimaryTranslation(string $termId, string $text): int;

    /** Replace the pinned example's translation — used when the generator wrote it in the wrong language. */
    public function setPinnedExampleTranslation(string $termId, string $text): int;
}
