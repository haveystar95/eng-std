<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Application\Command;

use App\Modules\Shared\Domain\Service\LanguagePurity;
use App\Modules\Vocabulary\Application\Dto\RelabelOutcome;
use App\Modules\Vocabulary\Application\Dto\RepairedTranslationRow;
use App\Modules\Vocabulary\Application\Port\TranslationLabelWriter;

/**
 * The label half of the language repair, after the fact.
 *
 * `RepairContentLanguageHandler` asked the model for Russian, wrote Russian into the row, and left
 * the row labelled `uk` or `de` — because {@see \App\Modules\Vocabulary\Application\Port\TermCurator}
 * had no language to move. 118 live rows ended up holding correct Russian under a foreign label,
 * which is what made a language-aware reader report "no Russian translation" for 118 terms that had
 * one all along. The writer is fixed; this cleans up behind it.
 *
 * TWO conditions, both required, and the second is the one that matters:
 *
 *  1. the text reads as $lang by {@see LanguagePurity} — Cyrillic majority, no Ukrainian-only
 *     letter. Deterministic, the same detector the barrier and the станок use;
 *  2. the row has been REWRITTEN since it was inserted (`updated_at > created_at`).
 *
 * Condition 1 alone is not enough to claim a row is Russian, and the detector says so itself: it
 * cannot see Ukrainian spelled entirely in letters Russian also has («працьовитий як бджола» passes
 * it). Condition 2 is what turns a guess into evidence — it says the repair already replaced this
 * text, so what is in the row now is the model's Russian answer and the label is simply stale. A
 * row nobody ever rewrote is original content, and original content in another language is
 * LEGITIMATE: a Ukrainian translation beside a Russian one is not corrupt, it is just not the row a
 * Russian speaker is shown. Choosing between them is the reader's job (D-2), not this command's.
 *
 * Idempotent: a second pass finds nothing, because the rows it touched now carry $lang.
 */
final readonly class RelabelRepairedTranslationsHandler
{
    public function __construct(
        private TranslationLabelWriter $translations,
        private LanguagePurity $purity = new LanguagePurity(),
    ) {}

    public function __invoke(RelabelRepairedTranslations $command): RelabelOutcome
    {
        $lang = $command->lang;

        $relabel = [];
        $relabelled = [];
        $kept = [];

        foreach ($this->translations->rowsNotLabelled($lang) as $row) {
            $why = $this->refusal($row, $lang);
            if ($why !== null) {
                $kept[] = [
                    'row_id' => $row->rowId,
                    'term' => $row->termText,
                    'lang' => $row->declaredLang,
                    'text' => $row->text,
                    'why' => $why,
                ];

                continue;
            }

            $relabel[] = $row->rowId;
            $relabelled[] = [
                'row_id' => $row->rowId,
                'term' => $row->termText,
                'from' => $row->declaredLang,
                'text' => $row->text,
            ];
        }

        if ($command->apply && $relabel !== []) {
            $this->translations->relabel($relabel, $lang);
        }

        return new RelabelOutcome($relabelled, $kept, $command->apply);
    }

    /** Why this row is left alone, or null when it is safe to relabel. */
    private function refusal(RepairedTranslationRow $row, string $lang): ?string
    {
        if (! $row->rewrittenSinceCreation) {
            return 'строку никогда не переписывали — это исходный контент на своём языке, он легален';
        }

        $foreign = $this->purity->ukrainianLetters($row->text);
        if ($foreign !== []) {
            return 'содержит буквы, которых нет в «' . $lang . '» (' . implode(' ', $foreign) . ')';
        }

        if (! $this->purity->isClean($lang, $row->text)) {
            return 'написано не письмом языка «' . $lang . '»';
        }

        return null;
    }
}
