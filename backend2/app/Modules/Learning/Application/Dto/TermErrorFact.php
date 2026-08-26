<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Dto;

/** A pair and how often it was answered wrong in one run — «труднее всего», before content. */
final readonly class TermErrorFact
{
    public function __construct(
        public string $termId,
        public int $errors,
    ) {}
}
