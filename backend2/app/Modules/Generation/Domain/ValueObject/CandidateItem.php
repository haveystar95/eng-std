<?php

declare(strict_types=1);

namespace App\Modules\Generation\Domain\ValueObject;

/**
 * One item exactly as a provider produced it — nothing normalised, nothing filled in.
 *
 * Deliberately tolerant of missing values: a bake-off exists to MEASURE how often a provider leaves
 * a field out, so a DTO that refused to hold an empty example would hide the very thing being
 * measured. Every field a model may fail to send is nullable, and the checks are what have opinions.
 */
final readonly class CandidateItem
{
    /**
     * @param  int  $position  0-based place in the answer — the axis the tail-of-a-long-answer
     *                         hypothesis is measured along
     * @param  string|null  $givenTerm  the term this item was asked to render, on the given-terms
     *                                  shape; null when the model chose the item itself
     * @param  list<string>  $options  wrong-answer options, as produced
     * @param  list<string>  $forms  accepted alternative spellings of the term, as produced
     * @param  list<RawDistractor>  $distractors  wrong versions of the card's example, as produced
     */
    public function __construct(
        public int $position,
        public string $text,
        public ?string $type = null,
        public ?string $translation = null,
        public ?string $example = null,
        public ?string $exampleTranslation = null,
        public ?string $transcription = null,
        public ?string $cefr = null,
        public array $options = [],
        /** @var list<string> other accepted spellings of the term (the mechanics/machinery shapes) */
        public array $forms = [],
        /** @var list<RawDistractor> wrong versions of the card's example (the machinery shape) */
        public array $distractors = [],
        public ?string $givenTerm = null,
        public ?string $sourceTermId = null,
    ) {}
}
