<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Dto;

/** A freshly generated example (+ its translation) with the usage for the spend record. */
final readonly class ExampleRegenResult
{
    /**
     * @param  string  $promptVersion  which versioned prompt file wrote this sentence — namespaced
     *        (`ex-regen.v2`), because the column it lands in also holds collection prompt versions
     *        and a bare `v2` there would name two different prompts. Reported by the adapter, which
     *        is the only place that knows which file it actually loaded.
     */
    public function __construct(
        public string $example,
        public ?string $exampleTranslation,
        public string $model,
        public ?int $tokensIn,
        public ?int $tokensOut,
        public string $promptVersion = 'ex-regen.v2',
    ) {}
}
