<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto;

/**
 * One bubble. The partner's carries its voice and no state; the learner's carries its state and no
 * voice — the marker stands at the learner's line, «прослушать» at the partner's.
 */
final readonly class WindowLineView
{
    public function __construct(
        public string $text,
        public string $translation,
        public ?string $audioId,
        public ?string $state,
    ) {}
}
