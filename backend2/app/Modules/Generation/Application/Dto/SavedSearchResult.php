<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Dto;

/**
 * What the one-tap save actually did — enough for the confirmation the learner sees.
 *
 * The folder's TITLE rides along because the confirmation names it («сохранено в „Сохранённые"»),
 * and the learner may have renamed that folder. A client that printed a hardcoded name would be
 * lying to exactly the person who changed it.
 */
final readonly class SavedSearchResult
{
    public function __construct(
        public string $termId,
        public string $collectionId,
        public string $collectionTitle,
        public bool $collectionIsDefault,
        /** False when the word was already in this folder — the tap was a replay, not a save. */
        public bool $added,
        /** False when the pair was already in the pool; the word resumes, it does not restart. */
        public bool $enrolled,
    ) {}
}
