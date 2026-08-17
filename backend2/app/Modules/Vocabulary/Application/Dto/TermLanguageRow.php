<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Application\Dto;

/**
 * One learner-language string that is actually shown to somebody, with everything needed to judge
 * it and to repair it.
 *
 * `declaredLang` is what the row CLAIMS to be written in: the `lang` column for a translation, and
 * — for an example translation, whose table has no language column — the source language of the
 * collections the term sits in. The two failure classes look different through this field: a
 * translation can be honestly labelled `uk` (nothing is corrupt, the wrong row is being shown),
 * while an example translation can only ever be a Russian field with Ukrainian in it.
 */
final readonly class TermLanguageRow
{
    public function __construct(
        public string $termId,
        public string $termText,
        public string $termType,
        public string $termLang,
        public string $field,             // translation | example_translation
        public string $rowId,             // term_translations.id or term_examples.id
        public string $declaredLang,
        public string $value,
        public ?string $exampleSentence,  // the sentence an example translation belongs to
        public bool $hasSiblingInDeclared, // another translation of this term already in the expected language
    ) {}
}
