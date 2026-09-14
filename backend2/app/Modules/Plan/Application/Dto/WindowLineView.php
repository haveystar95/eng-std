<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto;

/**
 * One bubble. Both carry their voice (DAY-UI-3: every line is voiced, in its speaker's voice); the
 * learner's also carries its state — the marker stands at the learner's line — and the frame it stands
 * on with the filler it was said with (GEN-2a, additive; null for a rescue line).
 */
final readonly class WindowLineView
{
    public function __construct(
        public string $text,
        public string $translation,
        public ?string $audioId,
        public ?string $state,
        public ?string $phraseRef = null,
        public ?string $filler = null,
    ) {}
}
