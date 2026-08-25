<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Dto;

/**
 * What a run is judged by. The three headline rates the станок exists to be measured on are scrap,
 * ambiguity and language — a run that writes a lot of rows while scrapping half of them is a prompt
 * problem, and without the rate it looks like success.
 */
final readonly class EnrichmentRunMetrics
{
    public function __construct(
        public int $termsSeen = 0,
        public int $termsFailed = 0,
        public int $distractorsProposed = 0,
        public int $distractorsRejected = 0,
        public int $distractorsWritten = 0,
        public int $variantsWritten = 0,
        public int $variantsRejected = 0,
        /**
         * Near-synonyms that PASSED validation — not synonyms written. The станок writes none: since
         * DG-1 the import is hardcoded to an empty list, because a synonym is a core product with one
         * producer. The name used to say `synonymsWritten`, and a metric that names an act nobody
         * performs is how a report comes to be read as evidence of it.
         *
         * It is worth keeping under that honest name: a replayed `mech-v14.2` (or a bake-off task on
         * a version that still asks) does propose synonyms, and what the deterministic half makes of
         * them is a number about the PROMPT, which is the thing the run is judging. Counted apart
         * from variants and never folded into them: the two products fail differently, and one
         * "extra answers" number would report a run with plenty of one and none of the other as
         * healthy.
         */
        public int $synonymsValidated = 0,
        public int $synonymsRejected = 0,
        public int $termsAmbiguous = 0,
        public int $termsLanguageFlagged = 0,
        public int $termsVariantConflict = 0,
        /** Ukrainian/суржик leakage in a Russian field — fixed by REGENERATING the item. */
        public int $termsUaLeakage = 0,
        /** Not a real word (typo, invented form, transliteration) — fixed by EDITING the wording. */
        public int $termsMisspelled = 0,
        /** Terms that HAVE a pinned example, i.e. the denominator for distractors-per-example. */
        public int $termsWithExample = 0,
        /**
         * Examples left with fewer than two valid distractors. `pick_correct` needs 1 correct + 2
         * wrong, so these examples cannot host that mode however good the rest of the run is — the
         * number is the decision input for asking the model for 3–4 instead of 2–3.
         */
        public int $examplesUnderTwoDistractors = 0,
    ) {}

    public function plus(self $other): self
    {
        return new self(
            termsSeen: $this->termsSeen + $other->termsSeen,
            termsFailed: $this->termsFailed + $other->termsFailed,
            distractorsProposed: $this->distractorsProposed + $other->distractorsProposed,
            distractorsRejected: $this->distractorsRejected + $other->distractorsRejected,
            distractorsWritten: $this->distractorsWritten + $other->distractorsWritten,
            variantsWritten: $this->variantsWritten + $other->variantsWritten,
            variantsRejected: $this->variantsRejected + $other->variantsRejected,
            synonymsValidated: $this->synonymsValidated + $other->synonymsValidated,
            synonymsRejected: $this->synonymsRejected + $other->synonymsRejected,
            termsAmbiguous: $this->termsAmbiguous + $other->termsAmbiguous,
            termsLanguageFlagged: $this->termsLanguageFlagged + $other->termsLanguageFlagged,
            termsVariantConflict: $this->termsVariantConflict + $other->termsVariantConflict,
            termsUaLeakage: $this->termsUaLeakage + $other->termsUaLeakage,
            termsMisspelled: $this->termsMisspelled + $other->termsMisspelled,
            termsWithExample: $this->termsWithExample + $other->termsWithExample,
            examplesUnderTwoDistractors: $this->examplesUnderTwoDistractors + $other->examplesUnderTwoDistractors,
        );
    }

    /** % of proposed distractor rows the validator threw away. */
    public function scrapRatePct(): float
    {
        return $this->distractorsProposed > 0
            ? round(100 * $this->distractorsRejected / $this->distractorsProposed, 1)
            : 0.0;
    }

    public function ambiguousRatePct(): float
    {
        return $this->ratePct($this->termsAmbiguous);
    }

    /** Any language problem — the three kinds together. */
    public function languageRatePct(): float
    {
        return $this->ratePct($this->termsLanguageFlagged);
    }

    public function misspelledRatePct(): float
    {
        return $this->ratePct($this->termsMisspelled);
    }

    public function uaLeakageRatePct(): float
    {
        return $this->ratePct($this->termsUaLeakage);
    }

    /** Valid distractors per example that HAS an example — the yield of one paid call. */
    public function distractorsPerExample(): float
    {
        return $this->termsWithExample > 0
            ? round($this->distractorsWritten / $this->termsWithExample, 2)
            : 0.0;
    }

    /** Share of examples that cannot host `pick_correct` (needs two wrong options). */
    public function underTwoDistractorsPct(): float
    {
        return $this->termsWithExample > 0
            ? round(100 * $this->examplesUnderTwoDistractors / $this->termsWithExample, 1)
            : 0.0;
    }

    private function ratePct(int $count): float
    {
        return $this->termsSeen > 0 ? round(100 * $count / $this->termsSeen, 1) : 0.0;
    }
}
