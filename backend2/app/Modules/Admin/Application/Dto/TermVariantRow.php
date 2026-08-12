<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Dto;

/** An alternative spelling/form the grader accepts as correct for this term. */
final readonly class TermVariantRow
{
    public function __construct(
        public string $text,
        public ?string $note,
        public string $generatorVersion,
    ) {}
}
