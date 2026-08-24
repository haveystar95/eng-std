<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Application\Port;

use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Vocabulary\Domain\ValueObject\Provenance;

/** Replaces a term's stored example with a new one. Vocabulary owns term_examples. */
interface TermExampleWriter
{
    /**
     * Rewrite the PINNED example in place — same row, same id — rather than deleting and re-inserting.
     *
     * The id is what `example_distractors.example_id` points at with `cascadeOnDelete`, so the old
     * delete+insert took every distractor of that example down with it (audit A1). The станок had paid
     * for those rows, and nothing said they were wrong: a new row id is not a judgement about content.
     *
     * What IS a judgement is `$dropDistractorSentences`, and it is made by the caller: a distractor is
     * a one-place break of a SPECIFIC sentence, so a distractor that no longer repairs to the new
     * example describes a sentence that no longer exists. Deciding that is Generation's business (it
     * owns the rule), deleting the rows is this port's. The rows are deleted, NOT suppressed —
     * a suppression means "a human decided this sentence is bad", and nothing here decided that; the
     * anchor moved under a row that was fine.
     *
     * The row also records WHO wrote the sentence. It used to be hardcoded `source = 'user'` with no
     * prompt version and no model, on text a model had just written (audit A3) — so the store held
     * AI examples indistinguishable from hand-typed ones, and the NULL provenance that is supposed to
     * mean «a writer forgot to stamp its content» was being produced by a writer that had the facts
     * and dropped them.
     *
     * @param  string  $translationLang  the language `$sentenceTranslation` is written in. Named by
     *         the caller and not inferred here: the caller is the one that just ASKED a model for a
     *         gloss in a particular language, and re-deriving it from the term's translations would
     *         be a second opinion that can disagree with the text it is labelling. Ignored when
     *         there is no translation to store.
     * @param  list<string>  $dropDistractorSentences  distractor sentences of the pinned example that
     *         the new sentence has orphaned. Empty (the default) keeps every distractor.
     * @param  string  $source  `ai` | `user` | `curated` — who wrote THIS sentence, not who asked
     * @param  Provenance|null  $provenance  the prompt version + model behind it; null only when the
     *         caller genuinely does not know (a human typing a sentence), never as a shortcut
     */
    public function replace(
        TermId $termId,
        string $sentence,
        ?string $sentenceTranslation,
        string $translationLang,
        array $dropDistractorSentences = [],
        string $source = 'user',
        ?Provenance $provenance = null,
    ): void;
}
