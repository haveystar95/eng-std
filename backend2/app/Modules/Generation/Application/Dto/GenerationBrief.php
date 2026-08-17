<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Dto;

use App\Modules\Shared\Domain\ValueObject\LanguageCode;

/** Everything the generator needs to produce a draft. Crosses into the adapter unchanged. */
final readonly class GenerationBrief
{
    /**
     * @param list<string> $levels       CEFR levels to span, e.g. ['A2','B1']
     * @param list<string> $excludeTexts  already-accepted texts a top-up must avoid re-producing;
     *                                     empty on the first pass. The adapter appends it to the
     *                                     user message as data — the frozen prompt file is untouched.
     */
    public function __construct(
        public string $prompt,
        public LanguageCode $sourceLang,
        public LanguageCode $targetLang,
        public array $levels,
        public int $size,
        public array $excludeTexts = [],
    ) {}
}
