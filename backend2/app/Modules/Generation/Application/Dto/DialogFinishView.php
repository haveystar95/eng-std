<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Dto;

/** The finish-dialog response: the native-language recap and how many target words were used. */
final readonly class DialogFinishView
{
    public function __construct(
        public string $summary,
        public int $wordsUsed,
        public int $wordsTotal,
    ) {}
}
