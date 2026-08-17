<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Dto;

use DateTimeImmutable;

/** The result of a collection's most recent concluded practice dialog. */
final readonly class LastCollectionDialogView
{
    public function __construct(
        public ?DateTimeImmutable $finishedAt,
        public int $wordsUsed,
        public int $wordsTotal,
        public ?string $summary,
    ) {}
}
