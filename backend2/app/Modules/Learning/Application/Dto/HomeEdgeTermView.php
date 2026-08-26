<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Dto;

/** One word of «На грани забывания»: what it is, and the day it falls due. */
final readonly class HomeEdgeTermView
{
    public function __construct(
        public string $termId,
        public string $text,
        public ?string $translation,
        /** Y-m-d in the learner's own timezone — the calendar day the repeat lands on. */
        public string $dueOn,
        /** Whole days from today to `dueOn`; 1 = «выпадет завтра». Always >= 1: today's are in the session. */
        public int $inDays,
    ) {}
}
