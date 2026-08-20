<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Service;

use App\Modules\Generation\Application\Command\BuildTermEnrichmentsHandler;
use App\Modules\Generation\Application\Port\EnrichmentJournal;
use App\Modules\Generation\Domain\Service\EnrichmentValidator;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Vocabulary\Application\Command\ReplaceTermExample;
use App\Modules\Vocabulary\Application\Command\ReplaceTermExampleHandler;
use App\Modules\Vocabulary\Application\Dto\DistractorAuditRow;
use App\Modules\Vocabulary\Application\Query\DistractorAuditReader;

/**
 * Swapping a term's example, with the machinery that hangs off it kept honest.
 *
 * Both paths that write a new example — «Новый пример» by hand and the automatic echo repair — used
 * to go straight at Vocabulary's replace command, and that command deleted the example row. Its
 * distractors are `cascadeOnDelete` on the example id, so every one of them died with it: content the
 * станок had been paid for, thrown away by a write that was only ever meant to fix a sentence
 * (audit A1).
 *
 * The row is now updated in place, which means the opposite risk arrives instead: a distractor is a
 * ONE-PLACE break of one specific sentence, so a distractor kept beside a different sentence is a
 * wrong-answer option belonging to a card that no longer exists. `pick_correct` would show it and the
 * learner would solve the card by noticing which sentence is about something else.
 *
 * So the rows are judged, here, by the rule this module already owns and `enrich:audit-distractors`
 * already applies: a distractor survives only while its stored span/correction still repairs it INTO
 * the example beside it. What survives is exactly what is still true; what does not is deleted (not
 * suppressed — a suppression records a human's verdict on a sentence, and nothing here judged the
 * sentence, its anchor moved). The term is then un-marked so the станок rebuilds a full set.
 */
final readonly class ExampleReplacement
{
    public function __construct(
        private DistractorAuditReader $distractors,
        private EnrichmentValidator $validator,
        private ReplaceTermExampleHandler $replace,
        private EnrichmentJournal $journal,
    ) {}

    /**
     * @param  string  $promptVersion  the prompt file that wrote this sentence, as the adapter reports
     *        it — the sentence is stamped with it, and with the model, so a defect sweep can find
     *        every example one prompt produced. The example writer used to record `source = 'user'`
     *        on model output and no stamp at all (audit A3).
     */
    public function apply(
        TermId $termId,
        string $sentence,
        ?string $sentenceTranslation,
        string $promptVersion,
        string $model,
    ): void {
        $orphaned = $this->orphaned($termId, $sentence);

        ($this->replace)(new ReplaceTermExample(
            $termId,
            $sentence,
            $sentenceTranslation,
            $orphaned,
            source: 'ai',
            promptVersion: $promptVersion,
            generationModel: $model,
        ));

        // Only when something was actually dropped: an unnecessary un-mark would re-send every
        // repaired term to the model on the next pass and pay for machinery it already has.
        if ($orphaned !== []) {
            $this->journal->clearMark($termId->value, BuildTermEnrichmentsHandler::VERSION);
        }
    }

    /**
     * The stored distractors the new sentence leaves describing nothing.
     *
     * @return list<string>  their sentences — the handle the writer deletes by
     */
    private function orphaned(TermId $termId, string $sentence): array
    {
        $out = [];
        foreach ($this->distractors->forTerm($termId->value) as $row) {
            if ($this->survives($row, $sentence)) {
                continue;
            }
            $out[] = $row->sentence;
        }

        return $out;
    }

    /**
     * A distractor survives a replacement only if it is still a distractor OF the new sentence: not
     * equal to it (that is the answer, not a wrong option), and repaired into it by the very span and
     * correction the row already carries. The label is deliberately NOT recomputed — a row that would
     * need a new one is a row whose explanation was written about a different sentence, and rewriting
     * it here would be this class inventing content instead of the станок producing it.
     */
    private function survives(DistractorAuditRow $row, string $sentence): bool
    {
        return ! $this->validator->sentenceEquals($row->sentence, $sentence)
            && $this->validator->repairsTo($row->sentence, $row->errorSpan, $row->correction, $sentence);
    }
}
