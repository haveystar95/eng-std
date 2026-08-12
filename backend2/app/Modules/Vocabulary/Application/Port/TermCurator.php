<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Application\Port;

use App\Modules\Vocabulary\Application\Dto\TermImpact;
use App\Modules\Shared\Domain\ValueObject\TermId;

/**
 * Curation writes over a term's content — the back-office side of the dictionary.
 *
 * Terms are GLOBAL and deduplicated, so every one of these edits is felt by every collection
 * holding the term and every learner studying it. That is the point (fixing a bad translation once
 * fixes it everywhere) and also the danger, which is why {@see impact()} exists: the panel states
 * the blast radius before the operator confirms.
 */
interface TermCurator
{
    /** Who would feel a change to this term. Null when there is no such (live) term. */
    public function impact(TermId $termId): ?TermImpact;

    /**
     * Update the term's own text/transcription and its primary translation. Null = leave alone.
     * Returns false when the term does not exist.
     */
    public function updateContent(TermId $termId, ?string $text, ?string $translation, ?string $ipa): bool;

    /**
     * Rewrite one example in place. Returns false when the example does not belong to the term.
     *
     * Everything the enrichment run derived FROM that sentence — its distractors above all — is
     * about a sentence that no longer exists, so it is dropped and the term is unmarked for the
     * станок to redo. Leaving stale distractors would show the learner a "find the mistake"
     * exercise built from text they are not looking at.
     */
    public function updateExample(TermId $termId, string $exampleId, string $sentence, ?string $translation): bool;

    /**
     * Delete ONE translation row by id. Returns false when the row does not belong to this term, or
     * when it is the term's last translation.
     *
     * Not expressible as {@see updateContent()}: that edits "the" translation, found by
     * `is_primary`, which is the wrong row precisely in the case this exists for — a term carrying
     * a correct Russian translation ALONGSIDE one in another language. The repair there is not a
     * rewrite, it is a removal, and it must name the row it removes.
     *
     * The last-translation guard is not politeness: a term with no translation is a card whose
     * question is blank, which is worse than a card whose question is in the wrong language.
     */
    public function dropTranslation(TermId $termId, string $translationId): bool;

    /**
     * Retire a term everywhere: soft-delete it, drop it out of every collection, and delete the
     * (user, term) progress rows.
     *
     * The append-only review log is NOT touched — it is history, and history does not change
     * because a term was retired. Returns false when there is no such live term.
     */
    public function retire(TermId $termId): bool;
}
