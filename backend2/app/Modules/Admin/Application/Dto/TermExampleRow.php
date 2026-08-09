<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Dto;

final readonly class TermExampleRow
{
    public function __construct(
        public string $sentence,
        public ?string $translation,
    ) {}
}
