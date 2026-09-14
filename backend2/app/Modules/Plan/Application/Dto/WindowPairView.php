<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto;

/**
 * One exchange of the dialogue tab: the partner's bubble (with its voice) and the learner's (with its
 * state), and the exchange's kind — `answer`, `ask` or `rescue` (GEN-2a, additive).
 */
final readonly class WindowPairView
{
    public function __construct(
        public int $step,
        public ?WindowLineView $partner,
        public ?WindowLineView $learner,
        public ?string $kind = null,
    ) {}
}
