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
        /**
         * Did this call put the word into the trainer's QUEUE?
         *
         * False for two different reasons and the client treats them alike, because the learner
         * does: «Сохранить» never asked for the queue (`enroll: false`), and «Учить сразу» on a word
         * already in it has nothing left to do — the word resumes, it does not restart.
         */
        public bool $enrolled,
    ) {}
}
