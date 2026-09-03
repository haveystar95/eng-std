<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\ValueObject;

/**
 * One card of a day, flattened to the four things the situation builder needs.
 *
 * A DTO of its own rather than passing Vocabulary's content view around: {@see
 * \App\Modules\Learning\Domain\Service\SituationalPrompt} is pure Domain and imports no other
 * module, and the flattening is one line at the one call site that has the content.
 */
final readonly class SituationalCandidate
{
    public function __construct(
        public string $termId,
        /** `hear` | `say` | `ask` | … — {@see \App\Modules\Generation\Domain\ValueObject\PlanShelf}. */
        public ?string $shelf,
        /** The scene ability this card serves (канон §8) — what a pair is matched on. */
        public ?string $skillRef,
        /** The card's own text, on the language being learned. */
        public string $text,
    ) {}
}
