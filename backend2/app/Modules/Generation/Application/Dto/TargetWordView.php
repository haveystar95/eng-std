<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Dto;

/** A target word of a dialog and whether the learner has used it yet. `used` is monotonic. */
final readonly class TargetWordView
{
    public function __construct(
        public string $termId,
        public string $text,
        public bool $used,
    ) {}
}
