<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Application\Query;

/**
 * Which terms were written by which prompt — the access path the provenance columns were added for.
 *
 * Reading by `prompt_version` is what makes regenerating a SUBSET of the catalogue possible: without
 * it the only options were "regenerate everything" (a paid run over content that is already fine) and
 * "regenerate nothing".
 */
interface StaleCoreReader
{
    /**
     * Term ids written by any of these prompt versions, ordered by id, after $afterId.
     *
     * The cursor is the term id and the order is the id order, because that is the only ordering that
     * is stable while the run is WRITING: a regenerated term's `updated_at` moves, and a pass ordered
     * by it would step over rows or revisit them.
     *
     * @param  list<string>  $promptVersions  e.g. ['legacy', 'v8', 'v9']
     * @return list<string>
     */
    public function idsByPromptVersion(array $promptVersions, ?string $afterId = null, int $limit = 500): array;

    /** @param  list<string>  $promptVersions */
    public function countByPromptVersion(array $promptVersions): int;

    /**
     * Which of THESE terms were written by some prompt other than $promptVersion.
     *
     * The dedup-merge question, asked the other way round. A generation that lands on a term the
     * store already has brought a whole fresh core with it, paid for and thrown away; whether that
     * core is worth writing over the stored one is decided by the passport, and the honest test is
     * "is the stored passport the one that just answered". Not a list of known-bad versions: those
     * age (`legacy`, `v8`, `v9`, and tomorrow `v10`), while "not the one in front of me" does not.
     *
     * Equality is the reason this exists at all — a term already at the current version is left
     * alone, because re-writing equal content is churn on rows the reader may have just reviewed.
     *
     * @param  list<string>  $termIds
     * @return list<string>  in the order the store returns them; empty when nothing is stale
     */
    public function idsNotWrittenBy(array $termIds, string $promptVersion): array;
}
