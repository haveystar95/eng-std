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
        public int $termsAmbiguous = 0,
        public int $termsLanguageFlagged = 0,
        public int $termsVariantConflict = 0,
        /** Ukrainian/суржик leakage in a Russian field — fixed by REGENERATING the item. */
        public int $termsUaLeakage = 0,
        /** Not a real word (typo, invented form, transliteration) — fixed by EDITING the wording. */
        public int $termsMisspelled = 0,
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
            termsAmbiguous: $this->termsAmbiguous + $other->termsAmbiguous,
            termsLanguageFlagged: $this->termsLanguageFlagged + $other->termsLanguageFlagged,
            termsVariantConflict: $this->termsVariantConflict + $other->termsVariantConflict,
            termsUaLeakage: $this->termsUaLeakage + $other->termsUaLeakage,
            termsMisspelled: $this->termsMisspelled + $other->termsMisspelled,
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

    private function ratePct(int $count): float
    {
        return $this->termsSeen > 0 ? round(100 * $count / $this->termsSeen, 1) : 0.0;
    }
}
