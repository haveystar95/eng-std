<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Dto;

/** «Следующий повтор — 28 августа, 14 слов»: the next scheduled day, and how much lands on it. */
final readonly class HomeNextReviewView
{
    public function __construct(
        /** Y-m-d in the learner's own timezone. */
        public string $date,
        public int $count,
    ) {}
}
