<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Dto;

/**
 * A card's CORE as a model has just written it — the key, the pronunciation, the level and the one
 * example — ready to be put onto a term that already exists.
 *
 * It exists so the two paths that replace a core ({@see \App\Modules\Generation\Application\Command\RegenerateShowcaseHandler}
 * and the dedup-merge refresh inside {@see \App\Modules\Generation\Application\Command\ProcessGenerationHandler})
 * can hand {@see \App\Modules\Generation\Application\Service\CoreReplacement} the same thing. One
 * arrives as a `CandidateItem` from the content contract, the other as a `GeneratedItem` from the
 * draft; neither is the shape of "what a replacement needs", and passing seven loose strings twice is
 * how the two paths would drift apart.
 *
 * A null field means the model said nothing about it, never that it said "empty" — the writer leaves
 * the stored value alone.
 */
final readonly class FreshCore
{
    public function __construct(
        public string $translation,
        public ?string $ipa = null,
        public ?string $cefr = null,
        public ?string $example = null,
        public ?string $exampleTranslation = null,
    ) {}
}
