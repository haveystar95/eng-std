<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Dto;

use App\Modules\Generation\Domain\ValueObject\RawDistractor;
use App\Modules\Generation\Domain\ValueObject\RawLanguageNote;
use App\Modules\Generation\Domain\ValueObject\RawVariant;

/**
 * ONE model call's answer for ONE term — all four products in a single JSON, which is the point:
 * distractors, extra correct answers, the back-translation the ambiguity check reads, and the
 * model's lexis notes. Four separate calls would cost four times as much and, worse, would let the
 * distractors be written against a different reading of the term than the variants.
 *
 * Nothing here is trusted. It goes through EnrichmentValidator before it goes near a table.
 */
final readonly class EnrichmentPack
{
    /**
     * @param  list<RawDistractor>  $distractors
     * @param  list<RawVariant>  $variants
     * @param  list<RawLanguageNote>  $languageNotes
     * @param  bool  $backTranslationAsked  did the prompt behind this pack ask for the QA fields at
     *         all? A pack from a shape that produces no translations carries `null` because there was
     *         no question, not because the model failed to answer one — and the validator judges the
     *         two differently. Defaults to true: the shape that DOES ask is the older one, and a new
     *         caller has to say so deliberately rather than inherit silence.
     */
    public function __construct(
        public array $distractors,
        public array $variants,
        public ?string $backTranslation,
        public array $languageNotes,
        public string $model,
        public ?int $tokensIn,
        public ?int $tokensOut,
        public bool $backTranslationAsked = true,
    ) {}
}
