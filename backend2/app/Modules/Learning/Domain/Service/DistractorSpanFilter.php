<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\Service;

/**
 * The distractors an example can actually contribute to a card: ONE PER `error_span`.
 *
 * Two distractors sharing a span put two options on screen that differ from the example in the same
 * place — «Could you explain the fees?» beside «Could you explain fees?» — so the card stops asking
 * "which sentence is right" and starts asking "which spelling of this one word did we mean".
 * Whichever the learner picks, the underline afterwards points at the same fragment twice. One error
 * per card is the shape `pick_correct` is for.
 *
 * Extracted from {@see \App\Modules\Learning\Application\Service\StudyCardAssembler}, which still
 * calls it and is still the only place that BUILDS the options — but the rule now has to answer two
 * questions instead of one. The playability gate counts what this returns (counting raw rows would
 * let a term with two same-span distractors through the ≥2 check and then hand the assembler one
 * usable option — a two-option pick_correct card, i.e. a coin flip), and so does the back-office
 * content report: «сколько у термина ГОДНЫХ дистракторов» has to be the same number the card sees,
 * or the report says a term is stocked and the trainer refuses to deal it.
 *
 * Order is preserved, first occurrence wins, comparison is trim + lowercase: the client applies
 * exactly this rule to exactly this list, so both sides drop the same row.
 */
final class DistractorSpanFilter
{
    /**
     * @param  list<array{sentence: string, error_type: string, error_span: string, correction: string}>  $distractors
     * @return list<array{sentence: string, error_type: string, error_span: string, correction: string}>
     */
    public function usable(array $distractors): array
    {
        $spans = [];
        foreach ($distractors as $distractor) {
            $spans[] = $distractor['error_span'];
        }

        $kept = [];
        foreach ($this->keptIndexes($spans) as $index) {
            $kept[] = $distractors[$index];
        }

        return $kept;
    }

    /**
     * The same filter over the spans alone — what a reporting projection has, which selected
     * `error_span` and nothing else.
     *
     * @param  list<string>  $spans
     * @return list<string>
     */
    public function usableSpans(array $spans): array
    {
        $kept = [];
        foreach ($this->keptIndexes($spans) as $index) {
            $kept[] = $spans[$index];
        }

        return $kept;
    }

    /** @param  list<string>  $spans */
    public function countUsable(array $spans): int
    {
        return count($this->keptIndexes($spans));
    }

    /**
     * The positions that survive: first occurrence of each non-empty span, case- and
     * whitespace-insensitive. THE rule — every method above reads it, so there is one copy of it.
     *
     * @param  list<string>  $spans
     * @return list<int>
     */
    private function keptIndexes(array $spans): array
    {
        $seen = [];
        $kept = [];
        foreach ($spans as $index => $span) {
            $folded = mb_strtolower(trim($span));
            if ($folded === '' || isset($seen[$folded])) {
                continue;
            }
            $seen[$folded] = true;
            $kept[] = $index;
        }

        return $kept;
    }
}
